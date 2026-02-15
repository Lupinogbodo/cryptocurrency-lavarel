# Cryptocurrency Trading Platform - API

A RESTful API for a cryptocurrency trading platform built with Laravel 12, enabling users to buy and sell digital assets (BTC, ETH, USDT) using a Naira (₦) wallet with real-time exchange rates.

## Table of Contents

- [Quick Start](#quick-start)
- [System Architecture](#system-architecture)
- [Setup & Installation](#setup--installation)
- [Configuration](#configuration)
- [API Endpoints](#api-endpoints)
- [Database Schema](#database-schema)
- [Fee Structure](#fee-structure)
- [CoinGecko Integration](#coingecko-integration)
- [Error Handling](#error-handling)
- [Testing](#testing)
- [Trade-offs & Constraints](#trade-offs--constraints)
- [Development Notes](#development-notes)

---

## Quick Start

### Prerequisites

- PHP 8.2+
- Composer
- PostgreSQL 12+ (or SQLite for development)
- Git

### Installation

```bash
# 1. Clone and navigate to project
cd cryptocurrency-lavarel
composer install

# 2. Setup environment
cp .env.example .env
php artisan key:generate

# 3. Database setup
php artisan migrate --seed

# 4. Start the server
php artisan serve
```

Access the API at `http://localhost:8000/api`

### Quick Test

```bash
# Get current crypto rates (public endpoint)
curl http://localhost:8000/api/trades/rates

# Register a user
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

---

## System Architecture

### Design Principles

1. **RESTful API**: Clean, intuitive endpoints following HTTP conventions
2. **Stateless Authentication**: JWT tokens via Laravel Sanctum
3. **ACID Transactions**: Database transactions for atomic operations
4. **Rate Caching**: Reduce API calls with strategic caching
5. **Decimal Precision**: Use `decimal()` for all monetary values
6. **Error Handling**: Graceful failures with meaningful error messages

### High-Level Architecture

```
┌─────────────────────────────────────────────┐
│          Client Applications                │
└────────────────┬────────────────────────────┘
                 │
        ┌────────▼─────────┐
        │   Laravel API    │
        │  (HTTP Server)   │
        └────────┬─────────┘
                 │
    ┌────────────┼────────────┐
    │            │            │
┌───▼──┐  ┌──────▼────┐  ┌────▼────┐
│ Auth │  │Wallet/Trade│ │ CoinGecko│
│Logic │  │  Services  │ │   API    │
└──────┘  └────────────┘ └──────────┘
    │            │
    └────────────┼────────────┐
                 │            │
            ┌────▼─────┐ ┌─────▼────┐
            │PostgreSQL │ │  Redis   │
            │ Database  │ │  Cache   │
            └───────────┘ └──────────┘
```

### Core Models & Relationships

```
User
├── has_one: Wallet
├── has_many: Trade
├── has_many: Transaction
└── has_many: CryptoHolding (through Wallet)

Wallet
├── belongs_to: User
├── has_many: CryptoHolding
└── has_many: Transaction (implicit)

CryptoHolding
├── belongs_to: Wallet
└── tracks: BTC/ETH/USDT amounts

Trade
├── belongs_to: User
├── type: buy | sell
├── status: completed | pending | failed
└── records: rate snapshot at time of trade

Transaction
├── belongs_to: User
├── type: deposit | buy_crypto | sell_crypto
└── tracks: wallet balance changes
```

---

## Setup & Installation

### 1. Environment Configuration

Copy and edit `.env.example`:

```bash
cp .env.example .env
```

Key configurations:

```env
# Application
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (PostgreSQL recommended)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=crypto_trading
DB_USERNAME=admin
DB_PASSWORD=password

# CoinGecko API (free tier, no key required)
COINGECKO_API_KEY=
VERIFY_SSL=true

# Caching
CACHE_STORE=database
```

### 2. Database Setup

```bash
# Run migrations
php artisan migrate

# Seed with test data
php artisan migrate --seed
```

**Test Credentials** (after seeding):

```
Email: vincent@example.com
Password: password123
Initial Balance: ₦500,000
```

Other test accounts:
- alice@example.com (password123)
- bob@example.com (password123)

### 3. Start Server

```bash
php artisan serve
# Server runs on http://localhost:8000
```

### 4. Verify Installation

```bash
# Test public endpoint
curl http://localhost:8000/api/trades/rates
```

---

## Configuration

### Crypto Trading Settings

Located in [app/Http/Controllers/Api/TradeController.php](app/Http/Controllers/Api/TradeController.php):

```php
const SUPPORTED_CRYPTOS = ['btc', 'eth', 'usdt'];
const BUY_FEE_PERCENT = 2.0;
const SELL_FEE_PERCENT = 2.0;
const MINIMUM_TRANSACTION_AMOUNT = 5000; // ₦5,000
const MINIMUM_CRYPTO_AMOUNTS = [
    'btc' => 0.0001,
    'eth' => 0.001,
    'usdt' => 1,
];
```

### CoinGecko Service

Located in [app/Services/CoinGeckoService.php](app/Services/CoinGeckoService.php):

```php
const BASE_URL = 'https://api.coingecko.com/api/v3';
const RATE_CACHE_MINUTES = 5;
const GLOBAL_CACHE_MINUTES = 10;
const USD_TO_NGN_RATE = 1550; // Fallback conversion
```

---

## API Endpoints

All endpoints return JSON responses. Authenticated endpoints require:

```
Authorization: Bearer {token}
```

### Authentication Endpoints

#### POST `/api/auth/register`

Register a new user.

**Request:**

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response (201):**

```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2026-02-15T10:00:00Z"
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

**Validations:**
- Name: required, string, max 255 chars
- Email: required, email, unique
- Password: required, min 8 chars, confirmed

---

#### POST `/api/auth/login`

Authenticate user and receive token.

**Request:**

```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response (200):**

```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

**Errors:**
- 422: Invalid credentials

---

#### POST `/api/auth/logout`

Logout and revoke token. Requires authentication.

**Response (200):**

```json
{
  "message": "Logged out successfully"
}
```

---

#### GET `/api/auth/profile`

Get authenticated user's profile.

**Response (200):**

```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "email_verified_at": null,
  "created_at": "2026-02-15T10:00:00Z",
  "updated_at": "2026-02-15T10:00:00Z"
}
```

---

### Wallet Endpoints

#### GET `/api/wallet/balance`

Get wallet balance and crypto holdings.

**Response (200):**

```json
{
  "naira_balance": "500000.00000000",
  "holdings": [
    {
      "id": 1,
      "wallet_id": 1,
      "crypto_symbol": "BTC",
      "amount": "0.00500000",
      "created_at": "2026-02-15T10:00:00Z",
      "updated_at": "2026-02-15T10:00:00Z"
    },
    {
      "id": 2,
      "wallet_id": 1,
      "crypto_symbol": "USDT",
      "amount": "10.00000000",
      "created_at": "2026-02-15T11:00:00Z",
      "updated_at": "2026-02-15T11:00:00Z"
    }
  ]
}
```

---

#### POST `/api/wallet/add-funds`

Add funds to wallet (simulates bank transfer/deposit).

**Request:**

```json
{
  "amount": 50000
}
```

**Response (200):**

```json
{
  "message": "Funds added successfully",
  "balance": "550000.00000000"
}
```

**Validations:**
- Amount: required, numeric, min 100

**Errors:**
- 422: Validation failed, amount too small

---

#### GET `/api/wallet/transactions`

Get transaction history with pagination.

**Query Parameters:**
- `page` (int): Page number, default 1
- `per_page` (int): Items per page, default 20, max 100

**Usage:**

```
GET /api/wallet/transactions?page=1&per_page=20
```

**Response (200):**

```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "type": "deposit",
      "amount": "1000000.00000000",
      "description": "Initial deposit",
      "previous_balance": "0.00000000",
      "new_balance": "1000000.00000000",
      "created_at": "2026-02-10T10:00:00Z",
      "updated_at": "2026-02-10T10:00:00Z"
    }
  ],
  "pagination": {
    "total": 15,
    "per_page": 20,
    "current_page": 1,
    "last_page": 1
  }
}
```

---

### Trading Endpoints

#### GET `/api/trades/rates`

Get current exchange rates for all supported cryptos. **Public endpoint - no authentication required.**

**Response (200):**

```json
{
  "success": true,
  "data": {
    "btc": {
      "symbol": "BTC",
      "rate_ngn": 97000000,
      "rate_usd": 62.58
    },
    "eth": {
      "symbol": "ETH",
      "rate_ngn": 5200000,
      "rate_usd": 3.35
    },
    "usdt": {
      "symbol": "USDT",
      "rate_ngn": 1550,
      "rate_usd": 1.0
    }
  },
  "timestamp": "2026-02-15T10:00:00Z"
}
```

---

#### POST `/api/trades/buy`

Buy cryptocurrency with Naira from wallet.

**Request:**

```json
{
  "crypto_symbol": "btc",
  "amount": 0.001
}
```

**Response (201):**

```json
{
  "success": true,
  "message": "Purchase successful",
  "data": {
    "trade_id": 42,
    "type": "buy",
    "crypto": "BTC",
    "crypto_amount": "0.00100000",
    "rate": "97000000.00000000",
    "subtotal": "97000.00000000",
    "fee": "1940.00000000",
    "total_cost": "98940.00000000",
    "fee_percent": 2.0,
    "timestamp": "2026-02-15T10:30:45Z",
    "new_balance": "401060.00000000"
  }
}
```

**Fee Calculation:**
```
fee = (crypto_amount × current_rate) × 0.02 (2%)
total_cost = (crypto_amount × current_rate) + fee
```

---

#### POST `/api/trades/sell`

Sell cryptocurrency for Naira into wallet.

**Request:**

```json
{
  "crypto_symbol": "btc",
  "amount": 0.001
}
```

**Response (201):**

```json
{
  "success": true,
  "message": "Sale successful",
  "data": {
    "trade_id": 43,
    "type": "sell",
    "crypto": "BTC",
    "crypto_amount": "0.00100000",
    "rate": "97000000.00000000",
    "gross_proceeds": "97000.00000000",
    "fee": "1940.00000000",
    "net_proceeds": "95060.00000000",
    "fee_percent": 2.0,
    "timestamp": "2026-02-15T10:35:10Z",
    "new_balance": "496120.00000000"
  }
}
```

**Fee Calculation:**
```
gross_proceeds = crypto_amount × current_rate
fee = gross_proceeds × 0.02 (2%)
net_proceeds = gross_proceeds - fee
```

---

#### GET `/api/trades/history`

Get user's trade history with filtering and pagination.

**Query Parameters:**
- `page` (int): Page number, default 1
- `per_page` (int): Items per page, default 20, max 100
- `symbol` (string): Filter by crypto symbol (btc, eth, usdt)
- `type` (string): Filter by trade type (buy, sell)

**Usage:**

```
GET /api/trades/history?page=1&per_page=10&symbol=btc&type=buy
```

**Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 42,
      "user_id": 1,
      "type": "buy",
      "crypto_symbol": "btc",
      "amount": "0.00100000",
      "naira_amount": "98940.00000000",
      "rate": "97000000.00000000",
      "fee": "1940.00000000",
      "status": "completed",
      "created_at": "2026-02-15T10:30:45Z",
      "updated_at": "2026-02-15T10:30:45Z"
    }
  ],
  "pagination": {
    "total": 1,
    "per_page": 10,
    "current_page": 1,
    "last_page": 1
  }
}
```

---

## Database Schema

### Tables Overview

```sql
-- Users table
users (id, name, email, password, email_verified_at, created_at, updated_at)

-- Wallets table
wallets (id, user_id, naira_balance, created_at, updated_at)

-- Crypto Holdings
crypto_holdings (id, wallet_id, crypto_symbol, amount, created_at, updated_at)
  Unique: (wallet_id, crypto_symbol)

-- Trades
trades (
  id, user_id, type, crypto_symbol, amount, naira_amount,
  rate, fee, status, created_at, updated_at
)

-- Transactions
transactions (
  id, user_id, type, amount, description,
  previous_balance, new_balance, created_at, updated_at
)
```

---

## Fee Structure

### Design Decision: Percentage-Based Fees

We implemented a **2% percentage fee** on all trades:

**Rationale:**
1. **Industry Standard**: Most crypto exchanges charge 0.1-2.5% on trades
2. **Simplicity**: Easy to understand and calculate
3. **Scalability**: Fair for both small and large transactions

### Fee Breakdown

#### Buy Transaction

```
Scenario: User buys 0.001 BTC at ₦97,000,000/BTC

Purchase Amount:        ₦97,000
Fee (2%):              ₦1,940
Total Cost:            ₦98,940

User's Naira Balance:  -₦98,940
User's BTC Holding:    +0.001 BTC
```

#### Sell Transaction

```
Scenario: User sells 0.001 BTC at ₦97,000,000/BTC

Gross Proceeds:        ₦97,000
Fee (2%):             ₦1,940
Net Proceeds:         ₦95,060

User's Naira Balance:  +₦95,060
User's BTC Holding:    -0.001 BTC
```

---

## CoinGecko Integration

### API Configuration

```php
// Free tier endpoint (no authentication needed)
https://api.coingecko.com/api/v3/simple/price

// Supported currencies: NGN (Nigerian Naira)
// Supported cryptos: bitcoin (BTC), ethereum (ETH), tether (USDT)
```

### Rate Caching Strategy

```
Rate Cache Duration:   5 minutes
Global Data Cache:     10 minutes
```

**Benefit**: Reduces API calls to ~12/hour per user (from 120+)

### Error Handling

```
CoinGecko API Down?
  → Return 503 Service Unavailable
  → Log error with details
  → No stale data used (safety first)
```

---

## Error Handling

### HTTP Status Codes

| Code | Scenario | Example |
|------|----------|---------|
| 200 | Success (GET/POST update) | Get profile, add funds |
| 201 | Resource created | Registration, successful trade |
| 400 | Bad request | Malformed JSON |
| 401 | Unauthenticated | Missing/invalid token |
| 403 | Forbidden | Unauthorized access |
| 404 | Not found | Invalid endpoint |
| 422 | Validation failed | Insufficient balance, invalid input |
| 500 | Server error | Unexpected exception |
| 503 | Service unavailable | CoinGecko API down |

### Error Response Format

**Validation Error:**

```json
{
  "success": false,
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

**API Error:**

```json
{
  "success": false,
  "message": "Insufficient Naira balance",
  "required": "150000.00",
  "available": "50000.00"
}
```

---

## Testing

### Test Coverage

✅ **Authentication**: Registration, login, logout, profile
✅ **Wallet Operations**: Balance, add funds, transaction history  
✅ **Trading**: Buy, sell, history, filtering
✅ **Fee Calculations**: Buy/sell fees, precision
✅ **Validation**: Input constraints, business rules
✅ **Error Scenarios**: Insufficient funds, invalid input
✅ **Edge Cases**: Exact amounts, multiple users, holdings

### Run Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/AuthTest.php
php artisan test tests/Feature/WalletTest.php
php artisan test tests/Feature/TradeTest.php

# Run with verbose output
php artisan test --verbose

# Run specific test method
php artisan test tests/Feature/TradeTest.php --filter=test_buy_fee_calculation
```

### Test Results

- **Total Tests**: 40+ comprehensive test cases
- **Coverage**: All major endpoints and error scenarios
- **Seeded Data**: 3 test users with realistic trade histories

---

## Trade-offs & Constraints

### Time Constraints (Implementation Time: ~4 hours)

1. **No Real Blockchain**
   - ✅ Simplified architecture
   - ⚠️ Holdings stored in database only

2. **Synchronous Processing**
   - ✅ Instant confirmations
   - ⚠️ No background job queues

3. **No Email Verification**
   - ✅ Faster development
   - ⚠️ Any email accepted

4. **Single Base Currency**
   - ✅ Reduced complexity
   - ⚠️ Only Naira supported

5. **Static Fee Structure**
   - ✅ Simple calculations
   - ⚠️ No volumetric discounts

### Known Limitations

| Limitation | Impact | Workaround |
|------------|--------|-----------|
| Rate caching (5 min) | Stale prices during market swings | Accept ±5 min variance |
| No pending trades | Instant settlement only | Add status: pending/confirmed later |
| Single NGN currency | Can't buy with USD directly | User must exchange first |
| No withdrawal API | Funds never leave system | Add bank transfer integration |

---

## Development Notes

### Project Structure

```
cryptocurrency-lavarel/
├── app/
│   ├── Http/Controllers/Api/
│   │   ├── AuthController.php
│   │   ├── WalletController.php
│   │   └── TradeController.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Wallet.php
│   │   ├── CryptoHolding.php
│   │   ├── Trade.php
│   │   └── Transaction.php
│   └── Services/
│       └── CoinGeckoService.php
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── routes/
│   └── api.php
├── tests/
│   ├── Feature/
│   │   ├── AuthTest.php
│   │   ├── WalletTest.php
│   │   └── TradeTest.php
│   └── Unit/
└── storage/logs/
    └── laravel.log
```

### Key Technologies

- **Framework**: Laravel 12
- **Authentication**: Laravel Sanctum (JWT)
- **Database**: PostgreSQL with Eloquent ORM
- **External API**: CoinGecko (free tier)
- **Caching**: Redis / Database cache
- **Testing**: PHPUnit with Feature tests

### Development Time Breakdown

| Task | Time |
|------|------|
| Project setup & config | 20 min |
| Models & migrations | 30 min |
| Authentication endpoints | 40 min |
| Wallet operations | 35 min |
| Trading logic & fees | 60 min |
| Testing & validation | 45 min |
| Documentation | 30 min |
| **Total** | **~4 hours** |

---

## Summary

**Project**: Cryptocurrency Trading Platform API
**Technology**: Laravel 12, PHP 8.2, PostgreSQL
**Supported Cryptos**: BTC, ETH, USDT
**Base Currency**: Nigerian Naira (₦)
**Fee Structure**: 2% on buy/sell trades
**Status**: Production-ready architecture
**Test Coverage**: 40+ comprehensive tests
**Development Time**: ~4 hours

**Quick Links**:
- API Base URL: `http://localhost:8000/api`
- Test User: `vincent@example.com` / `password123`
- CoinGecko API: `https://api.coingecko.com/api/v3`

---

**Built with ❤️ using Laravel 12 & PostgreSQL**
