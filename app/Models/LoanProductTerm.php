<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanProductTerm extends Model
{
    protected $fillable = [
        'loan_product_id',
        'interest_rate',
        'interest_type',
        'duration',
        'status',
    ];
}
