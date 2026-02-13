<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\TradeController;
use App\Http\Controllers\Api\DataController;

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

Route::get('/data/global', [DataController::class, 'global']);
Route::get('/data/trending', [DataController::class, 'trending']);
Route::get('/data/gainers-losers', [DataController::class, 'gainersLosers']);
Route::get('/data/categories', [DataController::class, 'categories']);
Route::get('/data/markets', [DataController::class, 'markets']);

Route::get('/data/coins/{coinId}', [DataController::class, 'coinData']);
Route::get('/data/coins/{coinId}/history', [DataController::class, 'coinHistory']);
Route::get('/data/coins/{coinId}/market-chart', [DataController::class, 'coinMarketChart']);
Route::get('/data/coins/{coinId}/ohlc', [DataController::class, 'coinOHLC']);

Route::get('/data/exchanges/{exchangeId}', [DataController::class, 'exchangeData']);
Route::get('/data/exchanges/{exchangeId}/volume-chart', [DataController::class, 'exchangeVolumeChart']);
Route::get('/data/exchanges/{exchangeId}/tickers', [DataController::class, 'exchangeTickers']);

Route::get('/data/nfts/{nftId}', [DataController::class, 'nftData']);
Route::get('/data/nfts/{nftId}/market-chart', [DataController::class, 'nftMarketChart']);
Route::get('/data/nfts/{nftId}/tickers', [DataController::class, 'nftTickers']);
