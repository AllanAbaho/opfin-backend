<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\Transaction;
use App\Services\LoanService;
use App\Services\SmsService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckTransactionStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'opfin:check-transaction-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check transaction status from payment gateway API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('Checking transaction status');
        try {
            $merchantNumber = env('MOBILE_MONEY_MERCHANT_ID');
            $transactions = Transaction::whereIn('status', ['PENDING', 'UNDETERMINED'])->get();
            foreach ($transactions as $transaction) {
                $reference = $transaction->reference;

                // Concatenate the fields in the specified order
                $signatureData = $merchantNumber . $reference;

                // Load the private key (store this securely in your .env or config)
                $privateKey = env('MOBILE_MONEY_PRIVATE_KEY');
                if (!$privateKey) {
                    throw new \Exception("Private key not configured");
                }

                // Sign the data
                openssl_sign($signatureData, $signature, $privateKey, OPENSSL_ALGO_SHA256);

                // Base64 encode the signature
                $base64Signature = base64_encode($signature);

                $signature = $base64Signature;

                $url = env('MOBILE_MONEY_API') . '/doTransactionCheckStatus';
                $data = [
                    'merchant_number' => $merchantNumber,
                    'reference' => $reference,
                    'signature' => $signature,
                ];
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])->post($url, $data);

                if ($response->successful()) {
                    $data = $response->json();

                    // Log the response
                    Log::info('Transaction status check response', [
                        'reference' => $reference,
                        'response' => $data
                    ]);

                    if ($data['state'] === 'OK') {
                        $transaction->update([
                            'status' => $data['txDetails']['transactionStatus'],
                            'network_reference' => $data['txDetails']['networkRef']
                        ]);

                        // Update loan application if transaction succeeded
                        if ($data['txDetails']['transactionStatus'] == 'SUCCESSFUL') {
                            if ($transaction->type == 'Disbursement') {
                                $transaction->loanApplication->update([
                                    'status' => 'Disbursed',
                                    'disbursed_at' => now(),
                                ]);
                                $loanService = app(LoanService::class);
                                $loanService->createLoanFromApplication($transaction->loanApplication);
                            }
                            if ($transaction->type == 'Repayment') {
                                $schedules = $transaction->loan->schedule;
                                foreach ($schedules as $schedule) {
                                    $schedule->applyPayment($transaction->amount);
                                }
                                $loanService = app(LoanService::class);
                                $loanService->processCollection($transaction->loan->transaction);
                            }
                        } else if ($data['txDetails']['transactionStatus'] == 'FAILED') {
                            $transaction->loanApplication->update([
                                'status' => 'Rejected',
                                'rejected_at' => now(),
                            ]);
                        }
                    }
                }
            }
            return 1;
        } catch (\Exception $e) {
            Log::error('Transaction status check failed', [
                'reference' => $reference,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
    }
}
