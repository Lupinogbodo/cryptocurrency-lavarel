<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class SwaggerController extends Controller
{
    public function index()
    {
        $swagger = $this->getSwaggerJson();
        return view('swagger.index', ['spec' => $swagger]);
    }

    public function json()
    {
        return response()->json($this->getSwaggerJson());
    }

    private function getSwaggerJson()
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Cryptocurrency Trading API',
                'description' => 'REST API for cryptocurrency trading platform',
                'version' => '1.0.0',
            ],
            'servers' => [
                [
                    'url' => request()->getSchemeAndHttpHost() . '/api',
                    'description' => 'API Server',
                ],
            ],
            'paths' => $this->getPaths(),
            'components' => [
                'schemas' => $this->getSchemas(),
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
        ];
    }

    private function getPaths()
    {
        return [
            '/auth/register' => [
                'post' => [
                    'tags' => ['Authentication'],
                    'summary' => 'Register new user',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'email' => ['type' => 'string', 'format' => 'email'],
                                        'password' => ['type' => 'string'],
                                        'password_confirmation' => ['type' => 'string'],
                                    ],
                                    'required' => ['name', 'email', 'password', 'password_confirmation'],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'User registered successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/AuthResponse'],
                                ],
                            ],
                        ],
                        '422' => ['description' => 'Validation error'],
                    ],
                ],
            ],
            '/auth/login' => [
                'post' => [
                    'tags' => ['Authentication'],
                    'summary' => 'Login user',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'email' => ['type' => 'string', 'format' => 'email'],
                                        'password' => ['type' => 'string'],
                                    ],
                                    'required' => ['email', 'password'],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Login successful',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/AuthResponse'],
                                ],
                            ],
                        ],
                        '422' => ['description' => 'Invalid credentials'],
                    ],
                ],
            ],
            '/auth/logout' => [
                'post' => [
                    'tags' => ['Authentication'],
                    'summary' => 'Logout user',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => ['description' => 'Logged out successfully'],
                        '401' => ['description' => 'Unauthorized'],
                    ],
                ],
            ],
            '/auth/profile' => [
                'get' => [
                    'tags' => ['Authentication'],
                    'summary' => 'Get user profile',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'User profile',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/User'],
                                ],
                            ],
                        ],
                        '401' => ['description' => 'Unauthorized'],
                    ],
                ],
            ],
            '/wallet/balance' => [
                'get' => [
                    'tags' => ['Wallet'],
                    'summary' => 'Get wallet balance',
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Wallet balance',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Wallet'],
                                ],
                            ],
                        ],
                        '401' => ['description' => 'Unauthorized'],
                    ],
                ],
            ],
            '/wallet/add-funds' => [
                'post' => [
                    'tags' => ['Wallet'],
                    'summary' => 'Add funds to wallet',
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'amount' => ['type' => 'number', 'minimum' => 100],
                                    ],
                                    'required' => ['amount'],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Funds added successfully'],
                        '401' => ['description' => 'Unauthorized'],
                        '422' => ['description' => 'Validation error'],
                    ],
                ],
            ],
            '/wallet/transactions' => [
                'get' => [
                    'tags' => ['Wallet'],
                    'summary' => 'Get transaction history',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        [
                            'name' => 'page',
                            'in' => 'query',
                            'schema' => ['type' => 'integer', 'default' => 1],
                        ],
                        [
                            'name' => 'per_page',
                            'in' => 'query',
                            'schema' => ['type' => 'integer', 'default' => 20],
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Transaction history'],
                        '401' => ['description' => 'Unauthorized'],
                    ],
                ],
            ],
            '/trades/rates' => [
                'get' => [
                    'tags' => ['Trading'],
                    'summary' => 'Get current crypto rates',
                    'security' => [],
                    'responses' => [
                        '200' => [
                            'description' => 'Current rates',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Rates'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/data/global' => [
                'get' => [
                    'tags' => ['Market Data'],
                    'summary' => 'Get global cryptocurrency data',
                    'security' => [],
                    'responses' => [
                        '200' => ['description' => 'Global market data'],
                        '500' => ['description' => 'Error fetching data'],
                    ],
                ],
            ],
            '/data/trending' => [
                'get' => [
                    'tags' => ['Market Data'],
                    'summary' => 'Get trending coins and NFTs',
                    'security' => [],
                    'responses' => [
                        '200' => ['description' => 'Trending data'],
                        '500' => ['description' => 'Error fetching data'],
                    ],
                ],
            ],
            '/data/gainers-losers' => [
                'get' => [
                    'tags' => ['Market Data'],
                    'summary' => 'Get top gainers and losers in 24h',
                    'security' => [],
                    'responses' => [
                        '200' => ['description' => 'Gainers and losers data'],
                        '500' => ['description' => 'Error fetching data'],
                    ],
                ],
            ],
            '/data/categories' => [
                'get' => [
                    'tags' => ['Market Data'],
                    'summary' => 'Get all cryptocurrency categories',
                    'security' => [],
                    'responses' => [
                        '200' => ['description' => 'Categories list'],
                        '500' => ['description' => 'Error fetching data'],
                    ],
                ],
            ],
            '/data/markets' => [
                'get' => [
                    'tags' => ['Market Data'],
                    'summary' => 'Get all supported coins with market data',
                    'security' => [],
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                        ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 250]],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Markets data'],
                        '500' => ['description' => 'Error fetching data'],
                    ],
                ],
            ],
            '/data/coins/{coinId}' => [
                'get' => [
                    'tags' => ['Coins Data'],
                    'summary' => 'Get coin data by ID',
                    'security' => [],
                    'parameters' => [
                        ['name' => 'coinId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'example' => 'bitcoin']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Coin data'],
                        '404' => ['description' => 'Coin not found'],
                        '500' => ['description' => 'Error fetching data'],
                    ],
                ],
            ],
            '/data/coins/{coinId}/history' => [
                'get' => [
                    'tags' => ['Coins Data'],
                    'summary' => 'Get historical price data for a coin',
                    'security' => [],
                    'parameters' => [
                        ['name' => 'coinId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ['name' => 'date', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string', 'example' => 'dd-mm-yyyy']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Historical data'],
                        '400' => ['description' => 'Date parameter required'],
                        '500' => ['description' => 'Error fetching data'],
                    ],
                ],
            ],
            '/data/coins/{coinId}/market-chart' => [
                'get' => [
                    'tags' => ['Coins Data'],
                    'summary' => 'Get market chart data for a coin',
                    'security' => [],
                    'parameters' => [
                        ['name' => 'coinId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ['name' => 'days', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 7]],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Market chart data'],
                        '500' => ['description' => 'Error fetching data'],
                    ],
                ],
            ],
            '/data/coins/{coinId}/ohlc' => [
                'get' => [
                    'tags' => ['Coins Data'],
                    'summary' => 'Get OHLC candlestick data for a coin',
                    'security' => [],
                    'parameters' => [
                        ['name' => 'coinId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ['name' => 'days', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 7]],
                    ],
                    'responses' => [
                        '200' => ['description' => 'OHLC data'],
                        '500' => ['description' => 'Error fetching data'],
                    ],
                ],
            ],
            '/data/exchanges/{exchangeId}' => [
                'get' => [
                    'tags' => ['Exchanges Data'],
                    'summary' => 'Get exchange information',
                    'security' => [],
                    'parameters' => [
                        ['name' => 'exchangeId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Exchange data'],
                        '404' => ['description' => 'Exchange not found'],
                        '500' => ['description' => 'Error fetching data'],
                    ],
                ],
            ],
            '/data/exchanges/{exchangeId}/volume-chart' => [
                'get' => [
                    'tags' => ['Exchanges Data'],
                    'summary' => 'Get exchange volume chart data',
                    'security' => [],
                    'parameters' => [
                        ['name' => 'exchangeId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ['name' => 'days', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 7]],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Volume chart data'],
                        '500' => ['description' => 'Error fetching data'],
                    ],
                ],
            ],
            '/data/exchanges/{exchangeId}/tickers' => [
                'get' => [
                    'tags' => ['Exchanges Data'],
                    'summary' => 'Get exchange tickers',
                    'security' => [],
                    'parameters' => [
                        ['name' => 'exchangeId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Tickers data'],
                        '500' => ['description' => 'Error fetching data'],
                    ],
                ],
            ],
            '/data/nfts/{nftId}' => [
                'get' => [
                    'tags' => ['NFTs Data'],
                    'summary' => 'Get NFT data',
                    'security' => [],
                    'parameters' => [
                        ['name' => 'nftId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'NFT data'],
                        '404' => ['description' => 'NFT not found'],
                        '500' => ['description' => 'Error fetching data'],
                    ],
                ],
            ],
            '/data/nfts/{nftId}/market-chart' => [
                'get' => [
                    'tags' => ['NFTs Data'],
                    'summary' => 'Get NFT market chart data',
                    'security' => [],
                    'parameters' => [
                        ['name' => 'nftId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ['name' => 'days', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 7]],
                    ],
                    'responses' => [
                        '200' => ['description' => 'NFT market chart data'],
                        '500' => ['description' => 'Error fetching data'],
                    ],
                ],
            ],
            '/data/nfts/{nftId}/tickers' => [
                'get' => [
                    'tags' => ['NFTs Data'],
                    'summary' => 'Get NFT tickers',
                    'security' => [],
                    'parameters' => [
                        ['name' => 'nftId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'NFT tickers'],
                        '500' => ['description' => 'Error fetching data'],
                    ],
                ],
            ],
        ];
    }

    private function getSchemas()
    {
        return [
            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'AuthResponse' => [
                'type' => 'object',
                'properties' => [
                    'user' => ['$ref' => '#/components/schemas/User'],
                    'token' => ['type' => 'string'],
                ],
            ],
            'Wallet' => [
                'type' => 'object',
                'properties' => [
                    'naira_balance' => ['type' => 'string', 'format' => 'decimal'],
                    'holdings' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'crypto_symbol' => ['type' => 'string'],
                                'amount' => ['type' => 'string', 'format' => 'decimal'],
                            ],
                        ],
                    ],
                ],
            ],
            'Rates' => [
                'type' => 'object',
                'properties' => [
                    'rates' => [
                        'type' => 'object',
                        'properties' => [
                            'btc' => ['type' => 'number'],
                            'eth' => ['type' => 'number'],
                            'usdt' => ['type' => 'number'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
