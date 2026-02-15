<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use App\Models\CryptoHolding;
use App\Models\Trade;
use App\Models\Transaction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Test user 1: Demo user with funds and some holdings
        $user1 = User::factory()->create([
            'name' => 'Vincent Demo',
            'email' => 'vincent@example.com',
            'password' => bcrypt('password123'),
        ]);

        $wallet1 = Wallet::create([
            'user_id' => $user1->id,
            'naira_balance' => 500000, // ₦500,000 remaining
        ]);

        // Create transaction history for user 1
        Transaction::create([
            'user_id' => $user1->id,
            'type' => 'deposit',
            'amount' => 1000000,
            'description' => 'Initial deposit',
            'previous_balance' => 0,
            'new_balance' => 1000000,
            'created_at' => now()->subDays(5),
        ]);

        Transaction::create([
            'user_id' => $user1->id,
            'type' => 'buy_crypto',
            'amount' => 485000,
            'description' => 'Bought 0.005 BTC',
            'previous_balance' => 1000000,
            'new_balance' => 515000,
            'created_at' => now()->subDays(3),
        ]);

        Transaction::create([
            'user_id' => $user1->id,
            'type' => 'buy_crypto',
            'amount' => 15000,
            'description' => 'Bought 10.0 USDT',
            'previous_balance' => 515000,
            'new_balance' => 500000,
            'created_at' => now()->subDays(1),
        ]);

        // Create crypto holdings for user 1
        CryptoHolding::create([
            'wallet_id' => $wallet1->id,
            'crypto_symbol' => 'btc',
            'amount' => 0.005,
        ]);

        CryptoHolding::create([
            'wallet_id' => $wallet1->id,
            'crypto_symbol' => 'usdt',
            'amount' => 10,
        ]);

        // Create trade history for user 1
        Trade::create([
            'user_id' => $user1->id,
            'type' => 'buy',
            'crypto_symbol' => 'btc',
            'amount' => 0.005,
            'naira_amount' => 485000,
            'rate' => 97000000,
            'fee' => 9400,
            'status' => 'completed',
            'created_at' => now()->subDays(3),
        ]);

        Trade::create([
            'user_id' => $user1->id,
            'type' => 'buy',
            'crypto_symbol' => 'usdt',
            'amount' => 10,
            'naira_amount' => 15000,
            'rate' => 1550,
            'fee' => 300,
            'status' => 'completed',
            'created_at' => now()->subDays(1),
        ]);

        // Test user 2: New user, no activity
        $user2 = User::factory()->create([
            'name' => 'Alice Trader',
            'email' => 'alice@example.com',
            'password' => bcrypt('password123'),
        ]);

        Wallet::create([
            'user_id' => $user2->id,
            'naira_balance' => 0,
        ]);

        Transaction::create([
            'user_id' => $user2->id,
            'type' => 'deposit',
            'amount' => 250000,
            'description' => 'Initial setup',
            'previous_balance' => 0,
            'new_balance' => 250000,
            'created_at' => now()->subDays(1),
        ]);

        // Test user 3: Active trader with multiple holdings
        $user3 = User::factory()->create([
            'name' => 'Bob Investor',
            'email' => 'bob@example.com',
            'password' => bcrypt('password123'),
        ]);

        $wallet3 = Wallet::create([
            'user_id' => $user3->id,
            'naira_balance' => 1500000,
        ]);

        // Diversified holdings
        CryptoHolding::create([
            'wallet_id' => $wallet3->id,
            'crypto_symbol' => 'btc',
            'amount' => 0.02,
        ]);

        CryptoHolding::create([
            'wallet_id' => $wallet3->id,
            'crypto_symbol' => 'eth',
            'amount' => 0.5,
        ]);

        CryptoHolding::create([
            'wallet_id' => $wallet3->id,
            'crypto_symbol' => 'usdt',
            'amount' => 100,
        ]);

        // Transaction history for user 3
        Transaction::create([
            'user_id' => $user3->id,
            'type' => 'deposit',
            'amount' => 3000000,
            'description' => 'Initial deposit',
            'previous_balance' => 0,
            'new_balance' => 3000000,
            'created_at' => now()->subDays(10),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $amount = rand(200000, 500000);
            $prevBalance = $i === 0 ? 3000000 : (3000000 - ($amount * $i));

            Transaction::create([
                'user_id' => $user3->id,
                'type' => 'buy_crypto',
                'amount' => $amount,
                'description' => "Crypto purchase #{$i}",
                'previous_balance' => $prevBalance,
                'new_balance' => $prevBalance - $amount,
                'created_at' => now()->subDays(10 - ($i * 2)),
            ]);
        }

        // Multiple trades for user 3
        $tradeSymbols = ['btc', 'eth', 'usdt'];
        $rates = ['btc' => 97000000, 'eth' => 5200000, 'usdt' => 1550];
        $amounts = ['btc' => 0.005, 'eth' => 0.1, 'usdt' => 50];

        foreach ($tradeSymbols as $i => $symbol) {
            $amount = $amounts[$symbol];
            $rate = $rates[$symbol];
            $nairaAmount = $amount * $rate;
            $fee = $nairaAmount * 0.02;

            Trade::create([
                'user_id' => $user3->id,
                'type' => 'buy',
                'crypto_symbol' => $symbol,
                'amount' => $amount,
                'naira_amount' => $nairaAmount + $fee,
                'rate' => $rate,
                'fee' => $fee,
                'status' => 'completed',
                'created_at' => now()->subDays(5 - $i),
            ]);
        }

        // Original test user (kept for backward compatibility)
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('teststaff123'),
        ]);

        Wallet::create([
            'user_id' => $user->id,
            'naira_balance' => 100000,
        ]);
    }
}

