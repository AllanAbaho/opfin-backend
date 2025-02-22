<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanApplication extends Model
{
    protected $fillable = [
        'user_id',
        'loan_product_id',
        'loan_product_term_id',
        'institution_id',
        'amount',
        'status',
        'reason',
        'disbursed_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
    ];

    /**
     * Get the user that owns the loan application.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the loan product that the loan application belongs to.
     */
    public function loanProduct()
    {
        return $this->belongsTo(LoanProduct::class);
    }

    /**
     * Get the loan product term that the loan application belongs to.
     */
    public function loanProductTerm()
    {
        return $this->belongsTo(LoanProductTerm::class);
    }

    /**
     * Get the institution that the loan application belongs to.
     */
    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Get the loan associated with the loan application.
     */
    public function loan()
    {
        return $this->hasOne(Loan::class);
    }
}
