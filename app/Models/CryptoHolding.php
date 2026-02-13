<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CryptoHolding extends Model
{
    protected $fillable = [
        'wallet_id',
        'crypto_symbol',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
