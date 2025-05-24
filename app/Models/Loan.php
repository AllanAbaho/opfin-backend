<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class Loan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'loan_product_id',
        'loan_product_term_id',
        'institution_id',
        'loan_application_id',
        'amount',
        'status',
        'reason',
        'disbursed_at',
        'duration',
        'repayment_amount',
        'repayment_start_date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($loan) {
            $loan->createLoanSchedule();
        });
    }

    public function createLoanSchedule()
    {
        // Validate required fields
        if (!$this->repayment_amount || !$this->duration || !$this->repayment_start_date) {
            throw new \Exception('Missing required loan details.');
        }

        // Extract variables for clarity
        $repaymentAmount = $this->repayment_amount;
        $duration = $this->duration;
        $interestType = $this->loanProductTerm->interest_type;
        $repaymentFrequency = $this->loanProductTerm->repayment_frequency; // Assuming repayment_frequency is in months
        $interestRate = $this->loanProductTerm->interest_rate / 100; // Convert percentage to decimal
        $loanAmount = $this->amount;
        $repaymentStartDate = Carbon::parse($this->repayment_start_date);
        $interestCycle = $this->loanProductTerm->interest_cycle; // Weekly, Monthly.

        $interestRate = self::getMonthlyInterestRate($interestCycle, $interestRate);

        // Calculate the number of installments
        $numberOfInstallments = self::getInstallments($duration, $repaymentFrequency);

        // Calculate the number of days between installments
        $daysBetweenInstallments = self::getDays($repaymentFrequency);

        // Initialize the remaining balance
        $remainingBalance = $loanAmount;

        // Loop through each installment
        for ($i = 0; $i < $numberOfInstallments; $i++) {
            // Calculate interest and principal based on the interest type
            if ($interestType === 'Flat') {
                // Flat interest: Interest is fixed for each installment
                $interest = ($loanAmount * $interestRate) / $numberOfInstallments;
                $principal = $loanAmount / $numberOfInstallments;
            } elseif ($interestType === 'Amortization') {
                // Calculate the fixed repayment amount for amortization
                $interestRatePerPeriod = $interestRate / $numberOfInstallments;
                $repaymentAmount = ($loanAmount * $interestRatePerPeriod * pow(1 + $interestRatePerPeriod, $numberOfInstallments))
                    / (pow(1 + $interestRatePerPeriod, $numberOfInstallments) - 1);

                // Calculate interest and principal for the current installment
                $interest = $remainingBalance * $interestRatePerPeriod;
                $principal = $repaymentAmount - $interest;

                // Ensure the principal does not exceed the remaining balance
                if ($principal > $remainingBalance) {
                    $principal = $remainingBalance;
                    $repaymentAmount = $principal + $interest; // Adjust repayment amount for the last installment
                }
            } else {
                throw new \Exception('Unsupported interest type:' . $interestType);
            }

            // Update the remaining balance
            $remainingBalance -= $principal;

            // Create the loan schedule entry
            LoanSchedule::create([
                'loan_id' => $this->id,
                'user_id' => $this->user_id,
                'institution_id' => $this->institution_id,
                'due_date' => $repaymentStartDate->copy()->addDays($daysBetweenInstallments * $i),
                'principal' => $principal,
                'interest' => $interest,
                'balance' => $principal + $interest,
            ]);
        }
    }

    protected static function getRepaymentAmount($interestRate, $loanAmount, $interestType, $numberOfInstallments, $interestCycle): float
    {
        $interestRate = self::getMonthlyInterestRate($interestCycle, $interestRate);
        if ($interestType === 'Flat') {
            // Flat interest: Total repayment = Loan amount + (Loan amount * Interest rate)
            return $loanAmount + ($loanAmount * $interestRate);
        } elseif ($interestType === 'Amortization') {
            // Amortization: Calculate fixed repayment amount per installment
            // Calculate the fixed repayment amount for amortization
            $interestRatePerPeriod = $interestRate / $numberOfInstallments;
            $repaymentAmount = ($loanAmount * $interestRatePerPeriod * pow(1 + $interestRatePerPeriod, $numberOfInstallments))
                / (pow(1 + $interestRatePerPeriod, $numberOfInstallments) - 1);
            return $repaymentAmount * $numberOfInstallments;
        } else {
            throw new \Exception('Unsupported interest type.');
        }
    }

    protected static function getRepaymentStartDate(string $repaymentFrequency)
    {
        if ($repaymentFrequency == 'Weekly') {
            return now()->addDays(7);
        }
        return now()->addMonth();
    }

    protected static function getMonthlyInterestRate(string $interestCycle, float $interestRate): float
    {
        if ($interestCycle == 'Weekly') {
            return $interestRate * 4;
        }
        return $interestRate;
    }

    protected static function getInstallments(int $duration, string $frequency): int
    {
        $daysInFrequency = self::getDaysInFrequency($frequency);
        return round($duration / $daysInFrequency);
    }

    protected static function getDays(string $frequency): int
    {
        return self::getDaysInFrequency($frequency);
    }

    /**
     * Helper method to get the number of days in a repayment frequency.
     *
     * @param string $frequency
     * @return int
     * @throws \Exception
     */
    protected static function getDaysInFrequency(string $frequency): int
    {
        switch ($frequency) {
            case 'Weekly':
                return 7;
            case 'Monthly':
                return 30;
            default:
                throw new \Exception('Unsupported repayment frequency');
        }
    }

    /**
     * Get the user that owns the loan.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the loan product term that the loan belongs to.
     */
    public function loanProductTerm()
    {
        return $this->belongsTo(LoanProductTerm::class);
    }

    /**
     * Get the loan schedules for the loan.
     */
    public function schedules()
    {
        return $this->hasMany(LoanSchedule::class);
    }

    public function totalOutstandingAmount()
    {
        return $this->schedules->sum('balance');
    }
}
