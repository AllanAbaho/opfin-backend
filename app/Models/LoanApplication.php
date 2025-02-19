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
}
