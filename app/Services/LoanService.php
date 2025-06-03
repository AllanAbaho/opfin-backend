<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\Transaction;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;

class LoanService
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Create a new loan from an approved application
     */
    public function createLoanFromApplication(LoanApplication $loanApplication): ?Loan
    {
        try {
            $loanAmount = $loanApplication->amount;
            $interestRate = $loanApplication->loanProductTerm->interest_rate / 100;
            $duration = $loanApplication->loanProductTerm->duration;
            $repaymentFrequency = $loanApplication->loanProductTerm->repayment_frequency;
            $numberOfInstallments = Loan::getInstallments($duration, $repaymentFrequency);
            $interestType = $loanApplication->loanProductTerm->interest_type;
            $interestCycle = $loanApplication->loanProductTerm->interest_cycle;

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
                'repayment_amount' => Loan::calculateRepaymentAmount(
                    $interestRate,
                    $loanAmount,
                    $interestType,
                    $numberOfInstallments,
                    $interestCycle
                ),
                'repayment_start_date' => Loan::calculateRepaymentStartDate($repaymentFrequency),
            ]);

            // Associate with transaction
            if ($transaction = Transaction::where('loan_application_id', $loanApplication->id)->first()) {
                $transaction->update(['loan_id' => $loan->id]);
                $this->processDisbursement($transaction, $loan);
            }

            return $loan;
        } catch (\Exception $e) {
            Log::error('Loan creation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Process all disbursement activities
     */
    public function processDisbursement(Transaction $transaction, Loan $loan): void
    {
        try {
            // Update Disbursement Account
            $disbursementAccount = Account::where('name', 'Disbursement')->first();
            $this->updateAccountBalance(
                $disbursementAccount,
                $transaction->amount,
                'Debit',
                $transaction->reference,
                'Loan disbursement'
            );

            // Update Loan Product Account
            $loanProductAccount = Account::where('loan_product_id', $loan->loan_product_id)->first();
            $this->updateAccountBalance(
                $loanProductAccount,
                $transaction->amount,
                'Credit',
                $transaction->reference,
                'Loan disbursement'
            );

            // Send disbursement notification
            $this->sendDisbursementNotification($loan);
        } catch (\Exception $e) {
            Log::error('Disbursement processing failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process all disbursement activities
     */
    public function processCollection(Transaction $transaction): void
    {
        try {
            // Update Disbursement Account
            $collectionAccount = Account::where('name', 'Collection')->first();
            $this->updateAccountBalance(
                $collectionAccount,
                $transaction->amount,
                'Credit',
                $transaction->reference,
                'Loan Repayment'
            );

            // Update Loan Product Account
            $loanProductAccount = Account::where('loan_product_id', $transaction->loan->loan_product_id)->first();
            $this->updateAccountBalance(
                $loanProductAccount,
                $transaction->amount,
                'Debit',
                $transaction->reference,
                'Loan Repayment'
            );

            // Send disbursement notification
            $this->sendCollectionNotification($transaction->loan);
        } catch (\Exception $e) {
            Log::error('Disbursement processing failed: ' . $e->getMessage());
            throw $e;
        }
    }


    /**
     * Update account balance and create journal entry
     */
    protected function updateAccountBalance(
        Account $account,
        float $amount,
        string $type,
        string $reference,
        string $description
    ): void {
        $previousBalance = $account->balance;

        $account->balance = $type === 'Debit'
            ? $account->balance - $amount
            : $account->balance + $amount;

        $account->save();

        JournalEntry::create([
            'account_id' => $account->id,
            'type' => $type,
            'amount' => $amount,
            'previous_balance' => $previousBalance,
            'current_balance' => $account->balance,
            'reference' => $reference,
            'description' => $description,
        ]);
    }

    /**
     * Prepare and send disbursement notification
     */
    protected function sendDisbursementNotification(Loan $loan): void
    {
        $message = $this->prepareDisbursementMessage($loan);
        $this->smsService->queueSms($loan->user->phone, $message);
    }

    /**
     * Prepare disbursement SMS message
     */
    protected function prepareDisbursementMessage(Loan $loan): string
    {
        return sprintf(
            "Hello %s, your loan of %s has been disbursed. Repayment amount: %s due on %s. Thank you for choosing us!",
            $loan->user->name,
            number_format($loan->amount),
            number_format($loan->repayment_amount),
            $loan->repayment_start_date->format('d M Y')
        );
    }

    /**
     * Prepare and send disbursement notification
     */
    protected function sendCollectionNotification(Loan $loan): void
    {
        $message = $this->prepareCollectionMessage($loan);
        $this->smsService->queueSms($loan->user->phone, $message);
    }

    /**
     * Prepare Collection SMS message
     */
    protected function prepareCollectionMessage(Loan $loan): string
    {
        $outstandingBalance = $loan->totalOutstandingAmount();
        $hasOutstandingBalance = $outstandingBalance > 0;

        $message = sprintf(
            "Hello %s, thank you for your loan repayment of %s.",
            $loan->user->name,
            number_format($loan->amount_paid ?? 0)
        );

        if ($hasOutstandingBalance) {
            $message .= sprintf(
                " Your outstanding balance is %s that is due on %s.",
                number_format($outstandingBalance),
                $loan->repayment_start_date->format('d M Y')
            );
        } else {
            $message .= " Your loan has been fully settled. We appreciate your business!";
        }

        return $message;
    }
}
