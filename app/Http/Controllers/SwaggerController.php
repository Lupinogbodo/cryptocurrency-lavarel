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
                    'description' => 'Get current exchange rates for supported cryptocurrencies (BTC, ETH, USDT) in NGN and USD',
                    'security' => [],
                    'responses' => [
                        '200' => [
                            'description' => 'Current rates retrieved successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/RatesResponse'],
                                ],
                            ],
                        ],
                        '500' => ['description' => 'Unable to fetch current rates'],
                    ],
                ],
            ],
            '/trades/buy' => [
                'post' => [
                    'tags' => ['Trading'],
                    'summary' => 'Buy cryptocurrency',
                    'description' => 'Buy crypto using Naira balance. A 2% fee is charged on purchases.',
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'crypto_symbol' => ['type' => 'string', 'enum' => ['btc', 'eth', 'usdt'], 'example' => 'btc'],
                                        'amount' => ['type' => 'number', 'example' => 0.5, 'description' => 'Amount of cryptocurrency to buy'],
                                    ],
                                    'required' => ['crypto_symbol', 'amount'],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Purchase successful',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/TradeResponse'],
                                ],
                            ],
                        ],
                        '401' => ['description' => 'Unauthorized'],
                        '422' => ['description' => 'Validation error - insufficient balance, minimum amount not met, or invalid crypto amount'],
                        '503' => ['description' => 'Unable to fetch current rate'],
                        '500' => ['description' => 'Trade failed'],
                    ],
                ],
            ],
            '/trades/sell' => [
                'post' => [
                    'tags' => ['Trading'],
                    'summary' => 'Sell cryptocurrency',
                    'description' => 'Sell crypto to receive Naira. A 2% fee is deducted from proceeds.',
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'crypto_symbol' => ['type' => 'string', 'enum' => ['btc', 'eth', 'usdt'], 'example' => 'btc'],
                                        'amount' => ['type' => 'number', 'example' => 0.5, 'description' => 'Amount of cryptocurrency to sell'],
                                    ],
                                    'required' => ['crypto_symbol', 'amount'],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Sale successful',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/TradeResponse'],
                                ],
                            ],
                        ],
                        '401' => ['description' => 'Unauthorized'],
                        '422' => ['description' => 'Validation error - insufficient crypto holdings, minimum amount not met, or invalid crypto amount'],
                        '503' => ['description' => 'Unable to fetch current rate'],
                        '500' => ['description' => 'Trade failed'],
                    ],
                ],
            ],
            '/trades/history' => [
                'get' => [
                    'tags' => ['Trading'],
                    'summary' => 'Get user trade history',
                    'description' => 'Retrieve paginated list of all trades made by the authenticated user',
                    'security' => [['bearerAuth' => []]],
                    'parameters' => [
                        [
                            'name' => 'page',
                            'in' => 'query',
                            'description' => 'Page number for pagination',
                            'schema' => ['type' => 'integer', 'default' => 1],
                        ],
                        [
                            'name' => 'per_page',
                            'in' => 'query',
                            'description' => 'Records per page',
                            'schema' => ['type' => 'integer', 'default' => 20],
                        ],
                        [
                            'name' => 'symbol',
                            'in' => 'query',
                            'description' => 'Filter by cryptocurrency symbol (btc, eth, usdt)',
                            'schema' => ['type' => 'string', 'enum' => ['btc', 'eth', 'usdt']],
                        ],
                        [
                            'name' => 'type',
                            'in' => 'query',
                            'description' => 'Filter by trade type',
                            'schema' => ['type' => 'string', 'enum' => ['buy', 'sell']],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Trade history retrieved successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/TradeHistoryResponse'],
                                ],
                            ],
                        ],
                        '401' => ['description' => 'Unauthorized'],
                        '500' => ['description' => 'Unable to fetch trade history'],
                    ],
                ],
            ],
            // Market / coin / exchange / NFT endpoints removed - service only provides rates now
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
            'RatesResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'data' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'symbol' => ['type' => 'string', 'example' => 'BTC'],
                                'rate_ngn' => ['type' => 'number', 'example' => 95000000],
                                'rate_usd' => ['type' => 'number', 'example' => 61290.32],
                            ],
                        ],
                    ],
                    'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'Trade' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'user_id' => ['type' => 'integer'],
                    'type' => ['type' => 'string', 'enum' => ['buy', 'sell']],
                    'crypto_symbol' => ['type' => 'string'],
                    'amount' => ['type' => 'string', 'format' => 'decimal'],
                    'naira_amount' => ['type' => 'string', 'format' => 'decimal'],
                    'rate' => ['type' => 'string', 'format' => 'decimal'],
                    'fee' => ['type' => 'string', 'format' => 'decimal'],
                    'status' => ['type' => 'string', 'enum' => ['completed', 'pending']],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                ],
            ],
            'TradeResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'message' => ['type' => 'string'],
                    'data' => [
                        'type' => 'object',
                        'properties' => [
                            'trade_id' => ['type' => 'integer'],
                            'type' => ['type' => 'string', 'enum' => ['buy', 'sell']],
                            'crypto' => ['type' => 'string'],
                            'crypto_amount' => ['type' => 'number'],
                            'rate' => ['type' => 'number'],
                            'subtotal' => ['type' => 'number'],
                            'fee' => ['type' => 'number'],
                            'total_cost' => ['type' => 'number'],
                            'fee_percent' => ['type' => 'number'],
                            'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                            'new_balance' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'TradeHistoryResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/Trade'],
                    ],
                    'pagination' => [
                        'type' => 'object',
                        'properties' => [
                            'total' => ['type' => 'integer'],
                            'per_page' => ['type' => 'integer'],
                            'current_page' => ['type' => 'integer'],
                            'last_page' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
