<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'crypto_symbol',
        'amount',
        'naira_amount',
        'rate',
        'fee',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'naira_amount' => 'decimal:8',
        'rate' => 'decimal:8',
        'fee' => 'decimal:8',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
