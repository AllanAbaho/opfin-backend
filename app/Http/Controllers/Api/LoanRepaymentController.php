<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
                return response()->json(['error' => 'There is a pending repayment transaction, please go ahead make payment.'], 400);
            }

            // Create a repayment transaction
            $repaymentTransaction = Transaction::create([
                'user_id' => $loan->user_id,
                'institution_id' => $loan->institution_id,
                'loan_id' => $loan->id,
                'loan_application_id' => $loan->loanApplication->id,
                'type' => 'Repayment',
                'amount' => $request->amount,
                'phone' => $loan->user->phone,
                'reference' => 'R-' . strtoupper(uniqid()),
                'status' => 'Pending',
            ]);
            // Make payment API call
            $paymentResponse = $this->initiateMobileMoneyPayIn(
                $repaymentTransaction,
            );
            Log::info("Mobile Money PayIn Response: ", [$paymentResponse]);
            if (!$paymentResponse['success']) {
                throw new \Exception($paymentResponse['message'] ?? 'Payment processing failed');
            }

            // Update transaction on success
            $updateData = [
                'status' => $paymentResponse['status'],
                'external_reference' => $paymentResponse['transaction_id'],
                'updated_at' => now(),
            ];

            $repaymentTransaction->update($updateData);
            return response()->json(['message' => 'Please go ahead and complete payment on your phone', 'success' => true]);
        } catch (\Exception $e) {
            Log::error('Error processing loan repayment: ' . $e->getMessage());
            // Update repaymentTransaction with failure status
            $repaymentTransaction->update([
                'status' => 'FAILED',
            ]);
            return response()->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }

    public function initiateMobileMoneyPayIn(Transaction $transaction): array
    {
        try {
            $apiUrl = env('MOBILE_MONEY_API') . '/doMobileMoneyPayIn';
            $merchantNumber = env('MOBILE_MONEY_MERCHANT_ID');
            $callBackUrl = route('handleCallback');
            // Prepare the request data
            $requestData = [
                'merchant_number' => $merchantNumber,
                'payer_number' => $transaction->phone,
                'amount' => $transaction->amount,
                'reference' => $transaction->reference,
                'description' => $transaction->type,
            ];

            // Generate signature if required
            $requestData['signature'] = $this->generateSignature(implode($requestData));
            $requestData['callback_url'] = $callBackUrl;

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(30) // 30 seconds timeout
                ->post($apiUrl, $requestData);
            if ($response->successful()) {
                $responseData = $response->json();

                if ($responseData['state'] === 'OK') {
                    return [
                        'success' => true,
                        'data' => $responseData,
                        'transaction_id' => $responseData['txDetails']['uniqueTransactionId'] ?? null,
                        'status' => $responseData['txDetails']['transactionStatus'] ?? null
                    ];
                }

                return [
                    'success' => false,
                    'error' => $responseData['message'] ?? 'API returned error',
                    'code' => $responseData['code'] ?? 'UNKNOWN'
                ];
            }

            return [
                'success' => false,
                'error' => 'API request failed',
                'status_code' => $response->status(),
                'response' => $response->body()
            ];
        } catch (\Exception $e) {
            Log::error('Mobile Money PayIn failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Generate request signature
     */
    protected function generateSignature($signatureData): string
    {
        // Load the private key (store this securely in your .env or config)
        $privateKey = env('MOBILE_MONEY_PRIVATE_KEY');
        if (!$privateKey) {
            throw new \Exception("Private key not configured");
        }

        // Sign the data
        openssl_sign($signatureData, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        // Base64 encode the signature
        return base64_encode($signature);
    }
}
