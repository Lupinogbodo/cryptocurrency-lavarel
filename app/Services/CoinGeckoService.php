<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CoinGeckoService
{
    const BASE_URL = 'https://api.coingecko.com/api/v3';
    const RATE_CACHE_MINUTES = 5;
    const GLOBAL_CACHE_MINUTES = 10;
    const DATA_CACHE_HOURS = 1;
    const USD_TO_NGN_RATE = 1550;

    private $cryptoMap = [
        'btc' => 'bitcoin',
        'eth' => 'ethereum',
        'usdt' => 'tether',
    ];

    /**
     * Make API request - FREE TIER DOESN'T NEED API KEY
     * Just remove COINGECKO_API_KEY from your .env file
     */
    private function makeRequest($endpoint, $params = [], $cacheMinutes = null)
    {
        try {
            // Free tier doesn't need API key - so we don't send any headers
            $headers = [];
            
            \Log::debug("CoinGecko API Request", [
                'endpoint' => $endpoint,
                'params' => $params,
            ]);

            $httpClient = Http::timeout(10)->withHeaders($headers);
            
            // Disable SSL verification for local development
            if (app()->environment('local') || env('VERIFY_SSL', true) === false) {
                $httpClient = $httpClient->withOptions([
                    'verify' => false,
                ]);
            }

            $response = $httpClient->get(self::BASE_URL . $endpoint, $params);
            
            if ($response->successful()) {
                \Log::debug("CoinGecko API Success", [
                    'endpoint' => $endpoint,
                    'status' => $response->status()
                ]);
                return $response->json();
            } else {
                \Log::error("CoinGecko API Failed", [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error("CoinGecko API error on {$endpoint}", [
                'message' => $e->getMessage(),
            ]);
        }
        return null;
    }

    public function getRate($symbol): ?float
    {
        $symbol = strtolower($symbol);

        if (!isset($this->cryptoMap[$symbol])) {
            return null;
        }

        $cacheKey = "crypto_rate_{$symbol}";
        $cachedRate = Cache::get($cacheKey);

        if ($cachedRate !== null) {
            return $cachedRate;
        }

        $data = $this->makeRequest('/simple/price', [
            'ids' => $this->cryptoMap[$symbol],
            'vs_currencies' => 'usd,ngn',
        ]);

        if ($data && isset($data[$this->cryptoMap[$symbol]])) {
            $coinData = $data[$this->cryptoMap[$symbol]];
            
            if (isset($coinData['ngn']) && $coinData['ngn'] > 0) {
                $rate = $coinData['ngn'];
            } elseif (isset($coinData['usd']) && $coinData['usd'] > 0) {
                $rate = $coinData['usd'] * self::USD_TO_NGN_RATE;
                \Log::info("Using USD to NGN conversion for {$symbol}", [
                    'usd_price' => $coinData['usd'],
                    'ngn_price' => $rate
                ]);
            } else {
                return null;
            }
            
            Cache::put($cacheKey, $rate, self::RATE_CACHE_MINUTES * 60);
            return $rate;
        }

        return null;
    }

    public function getAllRates(): array
    {
        $rates = [];
        foreach (array_keys($this->cryptoMap) as $symbol) {
            $rate = $this->getRate($symbol);
            if ($rate) {
                $rates[$symbol] = $rate;
            }
        }
        return $rates;
    }
}