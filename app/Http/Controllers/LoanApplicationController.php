<?php

namespace App\Http\Controllers;

use App\Models\LoanApplication;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class LoanApplicationController extends Controller
{
    /**
     * Store a newly created loan application in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
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
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Check if the user has any uncleared loans
            $unclearedLoan = Loan::where('user_id', $request->user_id)
                ->whereNotIn('status', ['Cleared', 'Completed'])
                ->first();

            if ($unclearedLoan) {
                return response()->json(['error' => 'User has an uncleared loan.'], 400);
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
                'approved_at' => null,
                'rejected_at' => null,
                'cancelled_at' => null,
            ]);

            return response()->json(['loan_application' => $loanApplication], 201);
        } catch (\Exception $e) {
            Log::error('Error creating loan application: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while creating the loan application.'], 500);
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
            return response()->json(['errors' => $validator->errors()], 422);
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
            return response()->json(['loan_application' => $loanApplication], 200);
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

        $loanAmount = $loanApplication->amount;
        $interestRate = $loanApplication->loanProductTerm->interest_rate / 100; // Convert percentage to decimal
        $duration = $loanApplication->loanProductTerm->duration; // Total duration in days
        $repaymentFrequency = $loanApplication->loanProductTerm->repayment_frequency; // Repayment frequency (e.g., 'Monthly', 'Weekly')
        $numberOfInstallments = Loan::getInstallments($duration, $repaymentFrequency); // Calculate number of installments
        $interestType = $loanApplication->loanProductTerm->interest_type; // Interest type (e.g., 'Amortization', 'Flat')
        $interestCycle = $loanApplication->loanProductTerm->interest_cycle; // Interest type (e.g., 'Amortization', 'Flat')
        // Create the loan

        Loan::create([
            'user_id' => $loanApplication->user_id,
            'loan_product_id' => $loanApplication->loan_product_id,
            'loan_product_term_id' => $loanApplication->loan_product_term_id,
            'institution_id' => $loanApplication->institution_id,
            'loan_application_id' => $loanApplication->id,
            'amount' => $loanAmount,
            'status' => 'Pending',
            'reason' => $loanApplication->reason,
            'disbursed_at' => null,
            'duration' => $duration,
            'repayment_amount' => Loan::getRepaymentAmount($interestRate, $loanAmount, $interestType, $numberOfInstallments, $interestCycle), // Correct repayment amount based on interest type
            'repayment_start_date' => Loan::getRepaymentStartDate($repaymentFrequency),
        ]);
    }
}
