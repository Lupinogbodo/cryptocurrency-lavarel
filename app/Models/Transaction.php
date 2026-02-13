<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'description',
        'previous_balance',
        'new_balance',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'previous_balance' => 'decimal:8',
        'new_balance' => 'decimal:8',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
