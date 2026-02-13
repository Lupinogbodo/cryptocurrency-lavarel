<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\TradeController;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/profile', [AuthController::class, 'profile']);

    Route::get('/wallet/balance', [WalletController::class, 'balance']);
    Route::post('/wallet/add-funds', [WalletController::class, 'addFunds']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);

    Route::post('/trades/buy', [TradeController::class, 'buy']);
    Route::post('/trades/sell', [TradeController::class, 'sell']);
    Route::get('/trades/history', [TradeController::class, 'history']);
});

Route::get('/trades/rates', [TradeController::class, 'rates']);

