<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
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
        'loan_application_id',
        'loan_id',
        'type',
        'amount',
        'phone',
        'reference',
        'external_reference',
        'network_reference',
        'status',
    ];

    /**
     * Get the loan application that owns the transaction.
     */
    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    /**
     * Get the loan that owns the transaction.
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the user that owns the transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the institution that owns the transaction.
     */
    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }
}
