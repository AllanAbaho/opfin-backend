<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class LoanRepaymentController extends Controller
{
    /**
     * Repay a loan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $loan_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function repay(Request $request, $loan_id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $loan = Loan::findOrFail($loan_id);
            if ($request->amount > $loan->totalOutstandingAmount()) {
                return response()->json(['error' => 'Amount exceeds the repayment amount.'], 400);
            }

            $transaction = Transaction::where('loan_id', $loan->id)
                ->where('type', 'Repayment')
                ->where('status', 'Pending')
                ->first();
            if ($transaction) {
                return response()->json(['error' => 'There is a pending repayment transaction.'], 400);
            }

            // Create a repayment transaction
            $repaymentTransaction = Transaction::create([
                'user_id' => $loan->user_id,
                'institution_id' => $loan->institution_id,
                'loan_id' => $loan->id,
                'type' => 'Repayment',
                'amount' => $request->amount,
                'phone' => $loan->user->phone,
                'reference' => 'R-' . strtoupper(uniqid()),
                'status' => 'Pending',
            ]);

            return response()->json(['transaction' => $repaymentTransaction], 201);
        } catch (\Exception $e) {
            Log::error('Error processing loan repayment: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
