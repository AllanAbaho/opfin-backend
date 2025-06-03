<?php

namespace App\Models;

use App\Services\LoanService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoanSchedule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'institution_id',
        'loan_id',
        'principal',
        'interest',
        'balance',
        'due_date',
    ];

    /**
     * Get the loan that owns the schedule.
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the user that owns the schedule.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the institution that owns the schedule.
     */
    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Process payment against loan schedule
     * 
     * @param float $paymentAmount
     * @return array Result of the payment processing
     */
    public function applyPayment(float $paymentAmount)
    {
        // Initialize variables
        $remainingPayment = $paymentAmount;
        $interestPaid = 0;
        $principalPaid = 0;


        // First apply payment to interest
        if ($this->interest > 0) {
            $interestPaid = min($this->interest, $remainingPayment);
            $this->interest -= $interestPaid;
            $remainingPayment -= $interestPaid;
        }

        // Then apply remaining payment to principal
        if ($remainingPayment > 0 && $this->principal > 0) {
            $principalPaid = min($this->principal, $remainingPayment);
            $this->principal -= $principalPaid;
            $remainingPayment -= $principalPaid;
        }

        // Update the balance
        $this->balance = $this->principal + $this->interest;

        // Save the changes
        $this->save();
    }
}
