<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'naira_balance',
    ];

    protected $casts = [
        'naira_balance' => 'decimal:8',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function holdings()
    {
        return $this->hasMany(CryptoHolding::class);
    }
}
