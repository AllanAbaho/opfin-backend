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
use App\Models\User;
use Illuminate\Support\Facades\Http;

class LoanApplicationController extends Controller
{

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
            $phone = User::find($request->user_id)->phone;
            if (str_starts_with($phone, '+25676') || str_starts_with($phone, '+25677') || str_starts_with($phone, '+25678')) {
                $network  = 'MTNMM';
            } else if (str_starts_with($phone, '+25670') || str_starts_with($phone, '+25674') || str_starts_with($phone, '+25675')) {
                $network  = 'AIRTELMM';
            }
            $accountBalance = 0;
            $getBalancesResponse = $this->getBalances();
            if ($getBalancesResponse['success']) {
                $data = collect($getBalancesResponse['balances']);
                $accountBalance = $data->firstWhere('name', $network)['amount'] ?? null;
            }
            if ($request->amount > $accountBalance) {
                throw new Exception('We cannot process this request at this time, please try an amount less than UGX ' . number_format($accountBalance));
            }
            // Check if the user has any uncleared loans
            $unclearedLoan = Loan::where('user_id', $request->user_id)
                ->whereNotIn('status', ['Cleared', 'Completed'])
                ->first();

            if ($unclearedLoan) {
                throw new Exception('You have an uncleared loan, please clear that first to be able to qualify another loan.');
            }
            // Check if the user has any uncleared loans
            $pendingApplication = LoanApplication::where('user_id', $request->user_id)
                ->where('status', 'Pending')
                ->first();

            if ($pendingApplication) {
                throw new Exception('You already have a pending application, please wait for it to get processed.');
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

            return response()->json(['message' => 'Application submitted successfully', 'data' => $loanApplication], 201);
        } catch (\Exception $e) {
            Log::error('Error creating loan application: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()]);
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
                // $this->createLoan($loanApplication);
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
            'reference' => 'PAYOUT-' . strtoupper(uniqid()),
            'status' => 'Pending',
        ]);
        $this->disburseLoan($loanApplication, $transaction);
    }

    public function disburseLoan(LoanApplication $loanApplication, Transaction $transaction)
    {
        try {
            // Mark transaction as processing
            $transaction->update([
                'status' => 'Processing',
                'processing_at' => now()
            ]);

            // Make payment API call
            $paymentResponse = $this->callPaymentApi(
                ltrim($transaction->phone, '+'),
                $transaction->amount,
                $transaction->reference,
            );
            Log::info("Mobile Money Payout Response: ", [$paymentResponse]);
            if (!$paymentResponse['success']) {
                throw new \Exception($paymentResponse['message'] ?? 'Payment processing failed');
            }

            // Update transaction on success
            $updateData = [
                'status' => $paymentResponse['status'],
                'external_reference' => $paymentResponse['transaction_id'],
                'updated_at' => now(),
            ];

            $transaction->update($updateData);
        } catch (\Exception $e) {
            // Log detailed error
            Log::error('Loan disbursement failed', [
                'error' => $e->getMessage(),
                'loan_id' => $loanApplication->id,
                'transaction_id' => $transaction->id,
            ]);

            // Update transaction with failure status
            $transaction->update([
                'status' => 'FAILED',
            ]);

            // Return detailed error response
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'transaction' => $transaction,
                'loan_application' => $loanApplication->fresh()
            ];
        }
    }


    public function callPaymentApi(string $phone, float $amount, string $reference)
    {
        // Request data
        $data = [
            "merchant_number" => env('MOBILE_MONEY_MERCHANT_ID'),
            "payee_number" => $phone,
            "reference" => $reference,
            "amount" => $amount,
            "description" => "Loan Disbursement",
            "callback_url" => route('handleCallback'),

        ];
        // Compute the signature
        try {
            // Concatenate the fields in the specified order
            $signatureData = $data['merchant_number'] .
                $data['payee_number'] .
                $data['amount'] .
                $data['reference'] .
                $data['description'];

            // Load the private key (store this securely in your .env or config)
            $privateKey = env('MOBILE_MONEY_PRIVATE_KEY');
            if (!$privateKey) {
                throw new \Exception("Private key not configured");
            }

            // Sign the data
            openssl_sign($signatureData, $signature, $privateKey, OPENSSL_ALGO_SHA256);

            // Base64 encode the signature
            $base64Signature = base64_encode($signature);

            // Add signature to the request data
            $data['signature'] = $base64Signature;
        } catch (\Exception $e) {
            Log::error("Signature generation failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Signature generation failed'];
        }

        // Send the request
        try {
            $response = Http::post(env('MOBILE_MONEY_API') . '/doMobileMoneyPayOut', $data);

            if ($response->successful()) {
                $responseData = $response->json();

                // Validate response structure
                if (isset($responseData['state']) && $responseData['state'] === 'OK') {
                    return [
                        'success' => true,
                        'transaction_id' => $responseData['txDetails']['uniqueTransactionId'] ?? null,
                        'status' => $responseData['txDetails']['transactionStatus'] ?? null,
                        'network_reference' => $responseData['txDetails']['networkRef'] ?? null,
                        'message' => $responseData['message'] ?? 'Operation was successful'
                    ];
                }

                return [
                    'success' => false,
                    'message' => $responseData['message'] ?? 'Unexpected response format',
                    'response' => $responseData
                ];
            } else {
                Log::error("API request failed", ['response' => $response->body()]);
                return ['success' => false, 'message' => 'API request failed', 'response' => $response->json()];
            }
        } catch (\Exception $e) {
            Log::error("API request exception: " . $e->getMessage());
            return ['success' => false, 'message' => 'API request exception'];
        }
    }

    public function getBalances()
    {
        // Concatenate the fields in the specified order
        $signatureData = env('MOBILE_MONEY_MERCHANT_ID');

        // Load the private key (store this securely in your .env or config)
        $privateKey = env('MOBILE_MONEY_PRIVATE_KEY');
        if (!$privateKey) {
            throw new \Exception("Private key not configured");
        }

        // Sign the data
        openssl_sign($signatureData, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        // Base64 encode the signature
        $base64Signature = base64_encode($signature);

        // Add signature to the request data
        $signature = $base64Signature;
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post(env('MOBILE_MONEY_API') . '/doGetBalances', [
                'merchant_number' => env('MOBILE_MONEY_MERCHANT_ID'),
                'signature' => $signature,
            ]);
            if ($response->successful()) {
                $data = $response->json();

                if ($data['state'] === 'OK') {
                    return [
                        'success' => true,
                        'balances' => $data['balances'],
                        'message' => $data['message']
                    ];
                }

                return [
                    'success' => false,
                    'message' => $data['message'] ?? 'API returned error'
                ];
            }

            return [
                'success' => false,
                'message' => 'API request failed with status: ' . $response->status()
            ];
        } catch (\Exception $e) {
            Log::error('Balance check failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Error making API request: ' . $e->getMessage()
            ];
        }
    }
}
