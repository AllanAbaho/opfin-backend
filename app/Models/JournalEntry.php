<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_id',
        'type',
        'amount',
        'previous_balance',
        'current_balance',
        'reference',
        'description',
    ];

    /**
     * Get the account that owns the journal entry.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
