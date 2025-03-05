<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\LoanRepayment;
use App\Models\Transaction;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class TransactionController extends Controller
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }
    /**
     * Approve a pending transaction.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve($id)
    {
        try {
            $transaction = Transaction::findOrFail($id);

            if ($transaction->status !== 'Pending') {
                return response()->json(['error' => 'Transaction is not in a pending state.'], 400);
            }

            $transaction->status = 'Approved';
            $transaction->external_reference = 'A-' . strtoupper(uniqid());
            $transaction->save();
            if ($transaction->type === 'Repayment') {
                $this->onRepayment($transaction);
            } elseif ($transaction->type === 'Disbursement') {
                $this->onDisbursement($transaction);
            }

            return response()->json(['transaction' => $transaction], 200);
        } catch (\Exception $e) {
            Log::error('Error approving transaction: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while approving the transaction.'], 500);
        }
    }

    protected function onDisbursement($transaction)
    {
        try {
            // Update Disbursement Account balance
            $disbursementAccount = Account::where('name', 'Disbursement')->first();
            $previousBalance = $disbursementAccount->balance;
            $disbursementAccount->balance += $transaction->loan->amount;
            $disbursementAccount->save();

            // Create journal entry for disbursement account
            JournalEntry::create([
                'account_id' => $disbursementAccount->id,
                'type' => 'Debit',
                'amount' => $transaction->loan->amount,
                'previous_balance' => $previousBalance,
                'current_balance' => $disbursementAccount->balance,
                'reference' => $transaction->reference,
                'description' => 'Loan disbursement',
            ]);

            // Debit the Funding Account
            $fundsAccount = Account::where('name', 'Funds')->first();
            $previousBalance = $fundsAccount->balance;
            $fundsAccount->balance -= $transaction->loan->amount;
            $fundsAccount->save();

            // Create journal entry for funding account
            JournalEntry::create([
                'account_id' => $fundsAccount->id,
                'type' => 'Credit',
                'amount' => $transaction->loan->amount,
                'previous_balance' => $previousBalance,
                'current_balance' => $fundsAccount->balance,
                'reference' => $transaction->reference,
                'description' => 'Loan disbursement',
            ]);

            // Queue SMS for disbursement
            $this->smsService->queueSms($transaction->loan->user->phone, 'Your loan has been disbursed.');
        } catch (\Exception $e) {
            Log::error('Error on disbursement: ' . $e->getMessage());
        }
    }

    protected function onRepayment($transaction)
    {
        DB::beginTransaction();
        try {
            // Create a loan repayment record
            LoanRepayment::create([
                'user_id' => $transaction->loan->user_id,
                'institution_id' => $transaction->loan->institution_id,
                'loan_id' => $transaction->loan->id,
                'amount' => $transaction->amount,
            ]);

            // Update Repayment Account balance
            $repaymentAccount = Account::where('name', 'Repayment')->first();
            $previousBalance = $repaymentAccount->balance;
            $repaymentAccount->balance += $transaction->amount;
            $repaymentAccount->save();

            // Create journal entry for repayment account
            JournalEntry::create([
                'account_id' => $repaymentAccount->id,
                'type' => 'Credit',
                'amount' => $transaction->amount,
                'previous_balance' => $previousBalance,
                'current_balance' => $repaymentAccount->balance,
                'reference' => $transaction->reference,
                'description' => 'Loan repayment',
            ]);

            // Credit the Funding Account
            $fundsAccount = Account::where('name', 'Funds')->first();
            $previousBalance = $fundsAccount->balance;
            $fundsAccount->balance += $transaction->amount;
            $fundsAccount->save();

            // Create journal entry for funding account
            JournalEntry::create([
                'account_id' => $fundsAccount->id,
                'type' => 'Debit',
                'amount' => $transaction->amount,
                'previous_balance' => $previousBalance,
                'current_balance' => $fundsAccount->balance,
                'reference' => $transaction->reference,
                'description' => 'Loan repayment',
            ]);

            // Clear loan schedules
            $this->clearLoanSchedules($transaction->loan, $transaction->amount);

            // Queue SMS for repayment
            $this->smsService->queueSms($transaction->loan->user->phone, 'Your loan repayment has been received.');
            DB::commit();
        } catch (\Exception $e) {
            Log::error('Error on repayment: ' . $e->getMessage());
            DB::rollBack();
        }
    }

    protected function clearLoanSchedules($loan, $amountPaid)
    {
        $remainingAmount = $amountPaid;

        foreach ($loan->schedules()->where('balance', '>', 0)->orderBy('due_date')->get() as $schedule) {
            if ($remainingAmount <= 0) {
                break;
            }

            $totalDue = $schedule->balance;

            if ($remainingAmount >= $totalDue) {
                // Fully pay off this schedule
                $schedule->balance = 0;
                $remainingAmount -= $totalDue;
            } else {
                // Partially pay off this schedule
                $schedule->balance -= $remainingAmount;
                $remainingAmount = 0;
            }
            $schedule->save();
        }
        return $remainingAmount;
    }
}
