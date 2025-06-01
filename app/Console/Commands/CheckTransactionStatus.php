<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\Transaction;
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
        $smsService = new  SmsService();
        $sent = $smsService->sendSms('256700460055', 'Hi Abaho');
        dd($sent);
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
                            $transaction->loanApplication->update([
                                'status' => 'Disbursed',
                                'disbursed_at' => now(),
                            ]);
                            $this->createLoan($transaction->loanApplication);
                        } else if ($data['txDetails']['transactionStatus'] == 'FAILED') {
                            $transaction->loanApplication->update([
                                'status' => 'Rejected',
                                'rejected_at' => now(),
                            ]);
                        }
                    } else {
                        $transaction->loanApplication->update([
                            'status' => 'Rejected',
                            'rejected_at' => now(),
                        ]);
                        $transaction->update([
                            'status' => 'FAILED',
                        ]);
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
            $smsService = new SmsService();
            $smsService->queueSms($transaction->loan->user->phone, $message);
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
}
