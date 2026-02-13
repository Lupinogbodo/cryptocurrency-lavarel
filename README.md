# Cryptocurrency Trading API

A simple RESTful API for a cryptocurrency trading platform built with Laravel 12, allowing users to buy and sell digital assets (BTC, ETH, USDT) using a Naira wallet.

## Setup & Installation

### Requirements
- PHP 8.2+
- Composer
- PostgreSQL 12+

### Installation Steps

1. **Clone and install dependencies:**
   ```bash
   cd cryptocurrency-lavarel
   composer install
   ```

2. **Configure environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database setup:**
   ```bash
   php artisan migrate --seed
   ```

4. **Start the server:**
   ```bash
   php artisan serve
   ```

Server runs on `http://localhost:8000`

### Access API Documentation
```
Swagger UI: http://localhost:8000/swagger
API Spec: http://localhost:8000/swagger.json
```

## Architecture & Design Decisions

### System Design
- **Sanctum Authentication**: Token-based API authentication for stateless endpoints
- **PostgreSQL Database**: Robust, ACID-compliant relational database
- **Decimal Precision**: All monetary values use `decimal(15,2)` for Naira and `decimal(18,8)` for crypto
- **Caching**: CoinGecko rates cached for 5 minutes to reduce API calls
- **Swagger Documentation**: Interactive API testing built-in

### CoinGecko Integration
- Uses free CoinGecko API (no authentication required)
- Fetches NGN (Naira) rates for BTC, ETH, USDT
- 5-minute rate caching to respect API rate limits
- Fallback: Returns error if API unavailable (no stale data used)
- Graceful error handling with proper logging

### Models & Relationships
- **User**: Manages authentication and relationships
- **Wallet**: One wallet per user, tracks Naira balance
- **CryptoHolding**: Tracks user's crypto amounts by symbol
- **Trade**: Records buy/sell transactions with rate snapshot
- **Transaction**: Audit trail of wallet balance changes

## API Documentation

### Base URL
```
http://localhost:8000/api
```

### Authentication
Include Bearer token in Authorization header:
```
Authorization: Bearer {token}
```

---

## Endpoints

### Authentication

#### Register
```
POST /auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}

Response (201):
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhai..."
}
```

#### Login
```
POST /auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}

Response (200):
{
  "user": { ... },
  "token": "..."
}
```

#### Logout
```
POST /auth/logout
Authorization: Bearer {token}

Response (200):
{
  "message": "Logged out successfully"
}
```

#### Get Profile
```
GET /auth/profile
Authorization: Bearer {token}

Response (200):
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "created_at": "2026-02-13T10:00:00Z"
}
```

---

### Wallet

#### Get Balance
```
GET /wallet/balance
Authorization: Bearer {token}

Response (200):
{
  "naira_balance": "50000.00",
  "holdings": [
    {
      "id": 1,
      "crypto_symbol": "BTC",
      "amount": "0.50000000"
    }
  ]
}
```

#### Add Funds
```
POST /wallet/add-funds
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": 50000
}

Response (200):
{
  "message": "Funds added successfully",
  "balance": "50000.00"
}
```

#### Transaction History
```
GET /wallet/transactions?page=1&per_page=20
Authorization: Bearer {token}

Response (200):
{
  "data": [
    {
      "id": 1,
      "type": "deposit",
      "amount": "50000.00",
      "description": "Deposit",
      "previous_balance": "0.00",
      "new_balance": "50000.00",
      "created_at": "2026-02-13T10:00:00Z"
    }
  ],
  "pagination": {
    "total": 10,
    "per_page": 20,
    "current_page": 1,
    "last_page": 1
  }
}
```

---

### Trading

#### Get Current Rates
```
GET /trades/rates

Response (200):
{
  "rates": {
    "btc": 2500000,
    "eth": 150000,
    "usdt": 1650
  }
}
---

## Testing

Run all tests:
```bash
php artisan test
```

Run specific test file:
```bash
php artisan test tests/Feature/AuthTest.php
```

### Test Coverage
- User registration and authentication
- Rate fetching

---

## Error Handling

All errors return appropriate HTTP status codes:

| Status | Scenario |
|--------|----------|
| 400 | Invalid input or insufficient balance |
| 401 | Unauthorized (missing/invalid token) |
| 403 | Forbidden |
| 404 | Resource not found |
| 422 | Validation error |
| 500 | Server error |

Error response:
```json
{
  "error": "Insufficient balance"
}
```

---

## Trade-offs & Constraints

1. **Minimum Buy**: ₦1,000 to prevent spam transactions
2. **No real blockchain**: Simulated crypto holdings in database
3. **Synchronous trades**: No background job processing (trades complete immediately)
4. **Rate caching**: Could be stale by up to 5 minutes
5. **No user verification**: Email verification not implemented
6. **Single currency**: Only Naira supported (hardcoded in service)

---

## Development Notes

- **Time spent**: ~3-4 hours for core implementation
- **Database**: SQLite by default (configured in .env)
- **API response format**: Consistent JSON for all endpoints
- **Validation**: Input validation on all endpoints
- **Logging**: Errors logged to `storage/logs/laravel.log`


---

## Running the Application

```bash
php artisan serve
```

Visit `http://localhost:8000` for the API.

Test with curl:
```bash
# Register
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'

# Get rates
curl http://localhost:8000/api/trades/rates
```

---

Built with Laravel 12 & Sanctum
