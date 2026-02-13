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

    public function getGlobalData(): ?array
    {
        $cacheKey = 'coingecko_global_data';
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->makeRequest('/global');

        if ($data) {
            Cache::put($cacheKey, $data, self::GLOBAL_CACHE_MINUTES * 60);
            return $data;
        }

        return null;
    }

    public function getTrendingSearch(): ?array
    {
        $cacheKey = 'coingecko_trending';
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->makeRequest('/search/trending');

        if ($data) {
            Cache::put($cacheKey, $data, 60 * 60);
            return $data;
        }

        return null;
    }

    public function getTopGainersLosers(): ?array
    {
        $cacheKey = 'coingecko_gainers_losers';
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->makeRequest('/coins/markets', [
            'vs_currency' => 'usd',
            'order' => 'market_cap_desc',
            'per_page' => 100,
            'page' => 1,
            'sparkline' => false,
            'price_change_percentage' => '24h',
        ]);

        if ($data && is_array($data)) {
            usort($data, function($a, $b) {
                $aChange = $a['price_change_percentage_24h'] ?? 0;
                $bChange = $b['price_change_percentage_24h'] ?? 0;
                return $bChange <=> $aChange;
            });

            $result = [
                'top_gainers' => array_slice($data, 0, 10),
                'top_losers' => array_reverse(array_slice($data, -10, 10)),
            ];

            Cache::put($cacheKey, $result, 60 * 60);
            return $result;
        }

        return null;
    }

    public function getCategories(): ?array
    {
        $cacheKey = 'coingecko_categories';
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->makeRequest('/coins/categories');

        if ($data) {
            Cache::put($cacheKey, $data, self::DATA_CACHE_HOURS * 60 * 60);
            return $data;
        }

        return null;
    }

    public function getMarkets($page = 1, $perPage = 250): ?array
    {
        $cacheKey = "coingecko_markets_page_{$page}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $perPage = min($perPage, 250);

        $data = $this->makeRequest('/coins/markets', [
            'vs_currency' => 'usd',
            'order' => 'market_cap_desc',
            'per_page' => $perPage,
            'page' => $page,
            'sparkline' => true,
            'price_change_percentage' => '1h,24h,7d',
        ]);

        if ($data) {
            Cache::put($cacheKey, $data, 10 * 60);
            return $data;
        }

        return null;
    }

    public function getCoinData($coinId): ?array
    {
        $cacheKey = "coingecko_coin_{$coinId}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->makeRequest("/coins/{$coinId}", [
            'localization' => false,
            'tickers' => true,
            'market_data' => true,
            'community_data' => true,
            'developer_data' => true,
        ]);

        if ($data) {
            Cache::put($cacheKey, $data, self::DATA_CACHE_HOURS * 60 * 60);
            return $data;
        }

        return null;
    }

    public function getCoinHistory($coinId, $date): ?array
    {
        $cacheKey = "coingecko_history_{$coinId}_{$date}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->makeRequest("/coins/{$coinId}/history", [
            'date' => $date,
            'localization' => false,
        ]);

        if ($data) {
            Cache::put($cacheKey, $data, self::DATA_CACHE_HOURS * 60 * 60);
            return $data;
        }

        return null;
    }

    public function getCoinMarketChart($coinId, $days = 7): ?array
    {
        $cacheKey = "coingecko_market_chart_{$coinId}_{$days}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->makeRequest("/coins/{$coinId}/market_chart", [
            'vs_currency' => 'usd',
            'days' => $days,
            'interval' => $days <= 1 ? 'hourly' : 'daily',
        ]);

        if ($data) {
            Cache::put($cacheKey, $data, 60 * 60);
            return $data;
        }

        return null;
    }

    public function getCoinOHLC($coinId, $days = 7): ?array
    {
        $cacheKey = "coingecko_ohlc_{$coinId}_{$days}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $validDays = [1, 7, 14, 30, 90, 180, 365];
        $days = collect($validDays)->first(function($validDay) use ($days) {
            return $validDay >= $days;
        }) ?? 7;

        $data = $this->makeRequest("/coins/{$coinId}/ohlc", [
            'vs_currency' => 'usd',
            'days' => $days,
        ]);

        if ($data) {
            Cache::put($cacheKey, $data, 60 * 60);
            return $data;
        }

        return null;
    }

    public function getExchangeData($exchangeId): ?array
    {
        $cacheKey = "coingecko_exchange_{$exchangeId}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->makeRequest("/exchanges/{$exchangeId}");

        if ($data) {
            Cache::put($cacheKey, $data, self::DATA_CACHE_HOURS * 60 * 60);
            return $data;
        }

        return null;
    }

    public function getExchangeVolumeChart($exchangeId, $days = 7): ?array
    {
        $cacheKey = "coingecko_exchange_volume_{$exchangeId}_{$days}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->makeRequest("/exchanges/{$exchangeId}/volume_chart", [
            'days' => $days,
        ]);

        if ($data) {
            Cache::put($cacheKey, $data, 60 * 60);
            return $data;
        }

        return null;
    }

    public function getExchangeTickers($exchangeId, $page = 1): ?array
    {
        $cacheKey = "coingecko_exchange_tickers_{$exchangeId}_{$page}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->makeRequest("/exchanges/{$exchangeId}/tickers", [
            'page' => $page,
        ]);

        if ($data) {
            Cache::put($cacheKey, $data, 10 * 60);
            return $data;
        }

        return null;
    }

    public function getNFTData($nftId): ?array
    {
        $cacheKey = "coingecko_nft_{$nftId}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->makeRequest("/nfts/{$nftId}");

        if ($data) {
            Cache::put($cacheKey, $data, self::DATA_CACHE_HOURS * 60 * 60);
            return $data;
        }

        return null;
    }

    public function getNFTMarketChart($nftId, $days = 7): ?array
    {
        $cacheKey = "coingecko_nft_chart_{$nftId}_{$days}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $data = $this->makeRequest("/nfts/{$nftId}/market_chart", [
            'days' => $days,
        ]);

        if ($data) {
            Cache::put($cacheKey, $data, 60 * 60);
            return $data;
        }

        return null;
    }

    public function getNFTTickers($nftId): ?array
    {
        \Log::warning("getNFTTickers endpoint doesn't exist in CoinGecko API", [
            'nft_id' => $nftId
        ]);
        return null;
    }
}