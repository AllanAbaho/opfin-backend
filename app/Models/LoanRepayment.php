<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanRepayment extends Model
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
        'amount',
    ];

    /**
     * Get the loan that owns the repayment.
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the user that owns the repayment.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the institution that owns the repayment.
     */
    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }
}
