<?php

namespace App\Http\Controllers\Api;

use App\Services\CoinGeckoService;
use Illuminate\Http\Request;

class DataController
{
    private $coinGeckoService;

    public function __construct(CoinGeckoService $coinGeckoService)
    {
        $this->coinGeckoService = $coinGeckoService;
    }

    public function global()
    {
        $data = $this->coinGeckoService->getGlobalData();

        if (!$data) {
            return response()->json(['error' => 'Unable to fetch global data'], 500);
        }

        return response()->json($data);
    }

    public function trending()
    {
        $data = $this->coinGeckoService->getTrendingSearch();

        if (!$data) {
            return response()->json(['error' => 'Unable to fetch trending data'], 500);
        }

        return response()->json($data);
    }

    public function gainersLosers()
    {
        $data = $this->coinGeckoService->getTopGainersLosers();

        if (!$data) {
            return response()->json(['error' => 'Unable to fetch gainers/losers data'], 500);
        }

        return response()->json($data);
    }

    public function categories()
    {
        $data = $this->coinGeckoService->getCategories();

        if (!$data) {
            return response()->json(['error' => 'Unable to fetch categories'], 500);
        }

        return response()->json($data);
    }

    public function markets(Request $request)
    {
        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 250);

        $data = $this->coinGeckoService->getMarkets($page, $perPage);

        if (!$data) {
            return response()->json(['error' => 'Unable to fetch markets data'], 500);
        }

        return response()->json($data);
    }

    public function coinData($coinId)
    {
        $data = $this->coinGeckoService->getCoinData($coinId);

        if (!$data) {
            return response()->json(['error' => 'Coin not found'], 404);
        }

        return response()->json($data);
    }

    public function coinHistory($coinId, Request $request)
    {
        $date = $request->query('date');

        if (!$date) {
            return response()->json(['error' => 'Date parameter required (dd-mm-yyyy)'], 400);
        }

        $data = $this->coinGeckoService->getCoinHistory($coinId, $date);

        if (!$data) {
            return response()->json(['error' => 'Unable to fetch coin history'], 500);
        }

        return response()->json($data);
    }

    public function coinMarketChart($coinId, Request $request)
    {
        $days = $request->query('days', 7);

        $data = $this->coinGeckoService->getCoinMarketChart($coinId, $days);

        if (!$data) {
            return response()->json(['error' => 'Unable to fetch market chart'], 500);
        }

        return response()->json($data);
    }

    public function coinOHLC($coinId, Request $request)
    {
        $days = $request->query('days', 7);

        $data = $this->coinGeckoService->getCoinOHLC($coinId, $days);

        if (!$data) {
            return response()->json(['error' => 'Unable to fetch OHLC data'], 500);
        }

        return response()->json($data);
    }

    public function exchangeData($exchangeId)
    {
        $data = $this->coinGeckoService->getExchangeData($exchangeId);

        if (!$data) {
            return response()->json(['error' => 'Exchange not found'], 404);
        }

        return response()->json($data);
    }

    public function exchangeVolumeChart($exchangeId, Request $request)
    {
        $days = $request->query('days', 7);

        $data = $this->coinGeckoService->getExchangeVolumeChart($exchangeId, $days);

        if (!$data) {
            return response()->json(['error' => 'Unable to fetch volume chart'], 500);
        }

        return response()->json($data);
    }

    public function exchangeTickers($exchangeId, Request $request)
    {
        $page = $request->query('page', 1);

        $data = $this->coinGeckoService->getExchangeTickers($exchangeId, $page);

        if (!$data) {
            return response()->json(['error' => 'Unable to fetch exchange tickers'], 500);
        }

        return response()->json($data);
    }

    public function nftData($nftId)
    {
        $data = $this->coinGeckoService->getNFTData($nftId);

        if (!$data) {
            return response()->json(['error' => 'NFT not found'], 404);
        }

        return response()->json($data);
    }

    public function nftMarketChart($nftId, Request $request)
    {
        $days = $request->query('days', 7);

        $data = $this->coinGeckoService->getNFTMarketChart($nftId, $days);

        if (!$data) {
            return response()->json(['error' => 'Unable to fetch NFT market chart'], 500);
        }

        return response()->json($data);
    }

    public function nftTickers($nftId)
    {
        $data = $this->coinGeckoService->getNFTTickers($nftId);

        if (!$data) {
            return response()->json(['error' => 'Unable to fetch NFT tickers'], 500);
        }

        return response()->json($data);
    }
}
