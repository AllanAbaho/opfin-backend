<?php

namespace App\Http\Controllers\Api;

use App\Models\LoanApplication;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanProductTerm;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Institution;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Services\SmsService;

class LoanApplicationController extends Controller
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function index($id)
    {
        try {
            $applications = LoanApplication::where('user_id', $id)->with(['user', 'loanProduct', 'loanProductTerm', 'institution'])->get();
            return response()->json(['data' => $applications], 200);
        } catch (Exception $e) {
            return response()->json(['error', $e->getMessage()], 500);
        }
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'loan_product_id' => 'required|exists:loan_products,id',
            'loan_product_term_id' => 'required|exists:loan_product_terms,id',
            'institution_id' => 'required|exists:institutions,id',
            'amount' => 'required|numeric',
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new Exception($validator->errors()->first());
        }

        try {
            // Check if the user has any uncleared loans
            $unclearedLoan = Loan::where('user_id', $request->user_id)
                ->whereNotIn('status', ['Cleared', 'Completed'])
                ->first();

            if ($unclearedLoan) {
                throw new Exception('User has an uncleared loan.');
            }

            $loanApplication = LoanApplication::create([
                'user_id' => $request->user_id,
                'loan_product_id' => $request->loan_product_id,
                'loan_product_term_id' => $request->loan_product_term_id,
                'institution_id' => $request->institution_id,
                'amount' => $request->amount,
                'status' => 'Pending',
                'reason' => $request->reason,
                'disbursed_at' => null,
                'approved_at' => now(),
                'rejected_at' => null,
                'cancelled_at' => null,
            ]);
            $this->createTransaction($loanApplication);

            return response()->json(['data' => $loanApplication], 201);
        } catch (\Exception $e) {
            Log::error('Error creating loan application: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the status of a loan application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $id)
    {
        DB::beginTransaction();
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Approved,Rejected,Disbursed,Cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        try {
            $loanApplication = LoanApplication::findOrFail($id);

            if ($loanApplication->status !== 'Pending') {
                return response()->json(['error' => 'Loan application is not in a pending state.'], 400);
            }

            $loanApplication->status = $request->status;

            if ($request->status === 'Approved') {
                $loanApplication->approved_at = now();
                $this->createLoan($loanApplication);
            } elseif ($request->status === 'Rejected') {
                $loanApplication->rejected_at = now();
            } elseif ($request->status === 'Disbursed') {
                $loanApplication->disbursed_at = now();
            } elseif ($request->status === 'Cancelled') {
                $loanApplication->cancelled_at = now();
            }

            $loanApplication->save();
            DB::commit();
            return response()->json(['data' => $loanApplication], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating loan application status: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while updating the loan application status.'], 500);
        }
    }

    /**
     * Create a loan from an approved loan application.
     *
     * @param  \App\Models\LoanApplication  $loanApplication
     * @return void
     */
    protected function createLoan(LoanApplication $loanApplication)
    {
        try {


            $loanAmount = $loanApplication->amount;
            $interestRate = $loanApplication->loanProductTerm->interest_rate / 100; // Convert percentage to decimal
            $duration = $loanApplication->loanProductTerm->duration; // Total duration in days
            $repaymentFrequency = $loanApplication->loanProductTerm->repayment_frequency; // Repayment frequency (e.g., 'Monthly', 'Weekly')
            $numberOfInstallments = Loan::getInstallments($duration, $repaymentFrequency); // Calculate number of installments
            $interestType = $loanApplication->loanProductTerm->interest_type; // Interest type (e.g., 'Amortization', 'Flat')
            $interestCycle = $loanApplication->loanProductTerm->interest_cycle; // Interest type (e.g., 'Amortization', 'Flat')
            // Create the loan

            $loan = Loan::create([
                'user_id' => $loanApplication->user_id,
                'loan_product_id' => $loanApplication->loan_product_id,
                'loan_product_term_id' => $loanApplication->loan_product_term_id,
                'institution_id' => $loanApplication->institution_id,
                'loan_application_id' => $loanApplication->id,
                'amount' => $loanAmount,
                'status' => 'Disbursed',
                'reason' => $loanApplication->reason,
                'disbursed_at' => now(),
                'duration' => $duration,
                'repayment_amount' => Loan::getRepaymentAmount($interestRate, $loanAmount, $interestType, $numberOfInstallments, $interestCycle), // Correct repayment amount based on interest type
                'repayment_start_date' => Loan::getRepaymentStartDate($repaymentFrequency),
            ]);

            $transaction = Transaction::where('loan_application_id', $loanApplication->id)->first();
            $transaction->update(['loan_id' => $loan->id]);
            $this->onDisbursement($transaction, $loan);
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    protected function onDisbursement($transaction, $loan)
    {
        try {
            // Update Disbursement Account balance
            $disbursementAccount = Account::where('name', 'Disbursement')->first();
            $previousDisbursementAccountBalance = $disbursementAccount->balance;
            $disbursementAccount->balance -= $transaction->loan->amount;
            $disbursementAccount->save();

            // Create journal entry for disbursement account
            JournalEntry::create([
                'account_id' => $disbursementAccount->id,
                'type' => 'Debit',
                'amount' => $transaction->loan->amount,
                'previous_balance' => $previousDisbursementAccountBalance,
                'current_balance' => $disbursementAccount->balance,
                'reference' => $transaction->reference,
                'description' => 'Loan disbursement',
            ]);

            // Update Disbursement Account balance
            $loanProductAccount = Account::where('loan_product_id', $loan->loan_product_id)->first();
            $previousloanProductAccountBalance = $loanProductAccount->balance;
            $loanProductAccount->balance += $transaction->loan->amount;
            $loanProductAccount->save();

            // Create journal entry for disbursement account
            JournalEntry::create([
                'account_id' => $loanProductAccount->id,
                'type' => 'Credit',
                'amount' => $transaction->loan->amount,
                'previous_balance' => $previousloanProductAccountBalance,
                'current_balance' => $loanProductAccount->balance,
                'reference' => $transaction->reference,
                'description' => 'Loan disbursement',
            ]);
            $message = $this->prepareDisbursementMessage($loan);

            // Queue SMS for disbursement
            $this->smsService->queueSms($transaction->loan->user->phone, $message);
        } catch (\Exception $e) {
            Log::error('Error on disbursement: ' . $e->getMessage());
        }
    }

    protected function prepareDisbursementMessage(Loan $loan)
    {
        return sprintf(
            "Hello %s, your loan of %s has been disbursed. Repayment amount: %s due on %s. Thank you for choosing us!",
            $loan->user->name,
            number_format($loan->amount),
            number_format($loan->repayment_amount),
            $loan->repayment_start_date->format('d M Y')
        );
    }

    public function getProducts()
    {
        try {
            $products = LoanProduct::all();
            return response()->json(['data' => $products], 200);
        } catch (Exception $e) {
            return response()->json(['error', $e->getMessage()], 500);
        }
    }

    public function getProductTerms($id)
    {
        try {
            $terms = LoanProductTerm::where('loan_product_id', $id)->get();
            return response()->json(['data' => $terms], 200);
        } catch (Exception $e) {
            return response()->json(['error', $e->getMessage()], 500);
        }
    }

    public function getInstitutions()
    {
        try {
            $institutions = Institution::all();
            return response()->json(['data' => $institutions], 200);
        } catch (Exception $e) {
            return response()->json(['error', $e->getMessage()], 500);
        }
    }

    public function createTransaction(LoanApplication $loanApplication)
    {
        $transaction =  Transaction::create([
            'user_id' => $loanApplication->user_id,
            'institution_id' => $loanApplication->institution_id,
            'loan_application_id' => $loanApplication->id,
            'loan_id' => null,
            'type' => 'Disbursement',
            'amount' => $loanApplication->amount,
            'phone' => $loanApplication->user->phone,
            'reference' => 'D-' . strtoupper(uniqid()),
            'status' => 'Pending',
        ]);
        $this->disburseLoan($loanApplication, $transaction);
    }

    public function disburseLoan(LoanApplication $loanApplication, Transaction $transaction)
    {
        try {
            // Simulate API call (replace with actual payment API integration)
            $paymentResponse = $this->callPaymentApi(
                $transaction->phone,
                $transaction->amount,
                $transaction->reference
            );

            // Update transaction on success
            $transaction->update([
                'status' => 'Completed',
                'external_reference' => $paymentResponse['transaction_id'] ?? null,
                'completed_at' => now(),
            ]);

            // You might also want to update the loan application status
            $loanApplication->update([
                'status' => 'Disbursed',
                'disbursed_at' => now(),
            ]);

            $this->createLoan($loanApplication);
        } catch (\Exception $e) {
            // Update transaction on failure
            $transaction->update([
                'status' => 'Failed',
                'failure_reason' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'transaction' => $transaction
            ];
        }
    }

    /**
     * Mock payment API (replace with actual API integration)
     */
    protected function callPaymentApi(string $phone, float $amount, string $reference): array
    {
        // This is a simulation - replace with actual API code like:
        // Flutterwave, Mpesa, Stripe, etc.

        // Simulate API delay
        sleep(2);

        // Simulate random failures (remove in production)
        if (rand(1, 10) === 1) { // 10% chance of failure for testing
            throw new \Exception("Payment provider timeout");
        }

        return [
            'transaction_id' => 'PYMT-' . strtoupper(uniqid()),
            'status' => 'success',
            'message' => 'Payment processed'
        ];
    }
}
