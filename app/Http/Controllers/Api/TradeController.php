<?php

namespace App\Http\Controllers\Api;

use App\Models\Trade;
use App\Models\CryptoHolding;
use App\Services\CoinGeckoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TradeController
{
    // Supported cryptocurrencies
    private const SUPPORTED_CRYPTOS = ['btc', 'eth', 'usdt'];

    // Trading fees (percentage)
    private const BUY_FEE_PERCENT = 2.0;
    private const SELL_FEE_PERCENT = 2.0;

    // Minimum transaction amounts in Naira
    private const MINIMUM_TRANSACTION_AMOUNT = 5000; // ₦5,000 minimum

    // Minimum crypto amounts to trade
    private const MINIMUM_CRYPTO_AMOUNTS = [
        'btc' => 0.0001,     // ~0.35 USD at current rates
        'eth' => 0.001,      // ~2 USD at current rates
        'usdt' => 1,         // $1 USDT
    ];

    private CoinGeckoService $coinGeckoService;

    public function __construct(CoinGeckoService $coinGeckoService)
    {
        $this->coinGeckoService = $coinGeckoService;
    }

    /**
     * Get current exchange rates for supported cryptocurrencies
     * Public endpoint - no authentication required
     */
    public function rates()
    {
        try {
            $rates = [];
            foreach (self::SUPPORTED_CRYPTOS as $symbol) {
                $rate = $this->coinGeckoService->getRate($symbol);
                $rates[$symbol] = [
                    'symbol' => strtoupper($symbol),
                    'rate_ngn' => $rate,
                    'rate_usd' => $rate ? round($rate / 1550, 8) : null,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $rates,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching rates', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch current rates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Buy cryptocurrency
     * User pays in Naira, receives crypto
     */
    public function buy(Request $request)
    {
        try {
            $validated = $request->validate([
                'crypto_symbol' => 'required|string|in:btc,eth,usdt',
                'amount' => 'required|numeric|min:0.0001',
            ]);

            $symbol = strtolower($validated['crypto_symbol']);
            $cryptoAmount = (float)$validated['amount'];

            // Validate minimum crypto amount
            if ($cryptoAmount < self::MINIMUM_CRYPTO_AMOUNTS[$symbol]) {
                throw ValidationException::withMessages([
                    'amount' => ["Minimum amount for {$symbol} is " . self::MINIMUM_CRYPTO_AMOUNTS[$symbol]],
                ]);
            }

            // Get current rate
            $rate = $this->coinGeckoService->getRate($symbol);
            if (!$rate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch current rate for ' . strtoupper($symbol),
                ], 503);
            }

            // Calculate total cost including fees
            $nairaBeforeFee = $cryptoAmount * $rate;
            $fee = $nairaBeforeFee * (self::BUY_FEE_PERCENT / 100);
            $totalNairaCost = $nairaBeforeFee + $fee;

            // Validate minimum transaction
            if ($totalNairaCost < self::MINIMUM_TRANSACTION_AMOUNT) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction amount too small. Minimum is ₦' . number_format(self::MINIMUM_TRANSACTION_AMOUNT),
                ], 422);
            }

            // Get user's wallet
            $user = $request->user();
            $wallet = $user->wallet;

            // Verify sufficient balance
            if ($wallet->naira_balance < $totalNairaCost) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient Naira balance',
                    'required' => number_format($totalNairaCost, 2),
                    'available' => number_format($wallet->naira_balance, 2),
                ], 422);
            }

            // Execute transaction in database transaction for atomicity
            $trade = DB::transaction(function () use (
                $user,
                $wallet,
                $symbol,
                $cryptoAmount,
                $rate,
                $nairaBeforeFee,
                $fee,
                $totalNairaCost
            ) {
                // Deduct from wallet
                $previousBalance = $wallet->naira_balance;
                $wallet->decrement('naira_balance', $totalNairaCost);
                $newBalance = $wallet->naira_balance;

                // Update or create crypto holding
                $holding = CryptoHolding::firstOrCreate(
                    ['wallet_id' => $wallet->id, 'crypto_symbol' => $symbol],
                    ['amount' => 0]
                );
                $holding->increment('amount', $cryptoAmount);

                // Record the trade
                $trade = Trade::create([
                    'user_id' => $user->id,
                    'type' => 'buy',
                    'crypto_symbol' => $symbol,
                    'amount' => $cryptoAmount,
                    'naira_amount' => $totalNairaCost,
                    'rate' => $rate,
                    'fee' => $fee,
                    'status' => 'completed',
                ]);

                // Record transaction
                $user->transactions()->create([
                    'type' => 'buy_crypto',
                    'amount' => $totalNairaCost,
                    'description' => "Bought {$cryptoAmount} " . strtoupper($symbol),
                    'previous_balance' => $previousBalance,
                    'new_balance' => $newBalance,
                ]);

                return $trade;
            });

            // Reload wallet to get updated balance
            $wallet->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Purchase successful',
                'data' => [
                    'trade_id' => $trade->id,
                    'type' => 'buy',
                    'crypto' => strtoupper($symbol),
                    'crypto_amount' => $cryptoAmount,
                    'rate' => $rate,
                    'subtotal' => $nairaBeforeFee,
                    'fee' => $fee,
                    'total_cost' => $totalNairaCost,
                    'fee_percent' => self::BUY_FEE_PERCENT,
                    'timestamp' => $trade->created_at->toIso8601String(),
                    'new_balance' => number_format($wallet->naira_balance, 2),
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Buy trade error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Trade failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sell cryptocurrency
     * User sends crypto, receives Naira
     */
    public function sell(Request $request)
    {
        try {
            $validated = $request->validate([
                'crypto_symbol' => 'required|string|in:btc,eth,usdt',
                'amount' => 'required|numeric|min:0.0001',
            ]);

            $symbol = strtolower($validated['crypto_symbol']);
            $cryptoAmount = (float)$validated['amount'];

            // Validate minimum crypto amount
            if ($cryptoAmount < self::MINIMUM_CRYPTO_AMOUNTS[$symbol]) {
                throw ValidationException::withMessages([
                    'amount' => ["Minimum amount for {$symbol} is " . self::MINIMUM_CRYPTO_AMOUNTS[$symbol]],
                ]);
            }

            // Get current rate
            $rate = $this->coinGeckoService->getRate($symbol);
            if (!$rate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to fetch current rate for ' . strtoupper($symbol),
                ], 503);
            }

            // Calculate proceeds after fees
            $nairaBeforeFee = $cryptoAmount * $rate;
            $fee = $nairaBeforeFee * (self::SELL_FEE_PERCENT / 100);
            $nairaProceed = $nairaBeforeFee - $fee;

            // Validate minimum transaction
            if ($nairaProceed < self::MINIMUM_TRANSACTION_AMOUNT) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction amount too small. Minimum is ₦' . number_format(self::MINIMUM_TRANSACTION_AMOUNT),
                ], 422);
            }

            // Get user's wallet
            $user = $request->user();
            $wallet = $user->wallet;

            // Check if user has crypto holdings
            $holding = CryptoHolding::where('wallet_id', $wallet->id)
                ->where('crypto_symbol', $symbol)
                ->first();

            if (!$holding || $holding->amount < $cryptoAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient ' . strtoupper($symbol) . ' holdings',
                    'required' => $cryptoAmount,
                    'available' => $holding?->amount ?? 0,
                ], 422);
            }

            // Execute transaction in database transaction for atomicity
            $trade = DB::transaction(function () use (
                $user,
                $wallet,
                $holding,
                $symbol,
                $cryptoAmount,
                $rate,
                $nairaBeforeFee,
                $fee,
                $nairaProceed
            ) {
                // Deduct crypto from holdings
                $holding->decrement('amount', $cryptoAmount);

                // Add to wallet
                $previousBalance = $wallet->naira_balance;
                $wallet->increment('naira_balance', $nairaProceed);
                $newBalance = $wallet->naira_balance;

                // Record the trade
                $trade = Trade::create([
                    'user_id' => $user->id,
                    'type' => 'sell',
                    'crypto_symbol' => $symbol,
                    'amount' => $cryptoAmount,
                    'naira_amount' => $nairaProceed,
                    'rate' => $rate,
                    'fee' => $fee,
                    'status' => 'completed',
                ]);

                // Record transaction
                $user->transactions()->create([
                    'type' => 'sell_crypto',
                    'amount' => $nairaProceed,
                    'description' => "Sold {$cryptoAmount} " . strtoupper($symbol),
                    'previous_balance' => $previousBalance,
                    'new_balance' => $newBalance,
                ]);

                return $trade;
            });

            // Reload wallet to get updated balance
            $wallet->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Sale successful',
                'data' => [
                    'trade_id' => $trade->id,
                    'type' => 'sell',
                    'crypto' => strtoupper($symbol),
                    'crypto_amount' => $cryptoAmount,
                    'rate' => $rate,
                    'gross_proceeds' => $nairaBeforeFee,
                    'fee' => $fee,
                    'net_proceeds' => $nairaProceed,
                    'fee_percent' => self::SELL_FEE_PERCENT,
                    'timestamp' => $trade->created_at->toIso8601String(),
                    'new_balance' => number_format($wallet->naira_balance, 2),
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Sell trade error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Trade failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's trade history
     * Authenticated endpoint
     */
    public function history(Request $request)
    {
        try {
            $page = $request->query('page', 1);
            $perPage = $request->query('per_page', 20);
            $symbol = $request->query('symbol'); // Optional filter
            $type = $request->query('type'); // Optional filter: 'buy' or 'sell'

            $query = $request->user()->trades();

            // Apply filters
            if ($symbol) {
                $query->where('crypto_symbol', strtolower($symbol));
            }

            if ($type && in_array($type, ['buy', 'sell'])) {
                $query->where('type', $type);
            }

            $trades = $query->latest()
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $trades->items(),
                'pagination' => [
                    'total' => $trades->total(),
                    'per_page' => $trades->perPage(),
                    'current_page' => $trades->currentPage(),
                    'last_page' => $trades->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Trade history error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch trade history',
            ], 500);
        }
    }
}
