<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class WalletController
{
    public function balance(Request $request)
    {
        $wallet = $request->user()->wallet;

        return response()->json([
            'naira_balance' => $wallet->naira_balance,
            'holdings' => $wallet->holdings,
        ]);
    }

    public function addFunds(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
        ]);

        $wallet = $request->user()->wallet;
        $previousBalance = $wallet->naira_balance;

        $wallet->increment('naira_balance', $request->amount);

        $request->user()->transactions()->create([
            'type' => 'deposit',
            'amount' => $request->amount,
            'previous_balance' => $previousBalance,
            'new_balance' => $wallet->naira_balance,
            'description' => 'Deposit',
        ]);

        return response()->json([
            'message' => 'Funds added successfully',
            'balance' => $wallet->naira_balance,
        ]);
    }

    public function transactions(Request $request)
    {
        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 20);

        $transactions = $request->user()->transactions()
            ->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $transactions->items(),
            'pagination' => [
                'total' => $transactions->total(),
                'per_page' => $transactions->perPage(),
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
            ],
        ]);
    }
}
