<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;
use App\Models\CryptoHolding;
use App\Services\CoinGeckoService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TradeTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->wallet = Wallet::create([
            'user_id' => $this->user->id,
            'naira_balance' => 1000000,
        ]);

        // Mock CoinGeckoService
        $this->mockCoinGeckoService();
    }

    protected function mockCoinGeckoService()
    {
        $mockService = \Mockery::mock(CoinGeckoService::class);
        $mockService->shouldReceive('getRate')
            ->with('btc')
            ->andReturn(95000000); // ₦95,000,000 per BTC
        $mockService->shouldReceive('getRate')
            ->with('eth')
            ->andReturn(5000000); // ₦5,000,000 per ETH
        $mockService->shouldReceive('getRate')
            ->with('usdt')
            ->andReturn(1550); // ₦1,550 per USDT

        $this->app->instance(CoinGeckoService::class, $mockService);
    }

    public function test_get_crypto_rates()
    {
        $response = $this->getJson('/api/trades/rates');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['symbol', 'rate_ngn', 'rate_usd']
            ],
            'timestamp'
        ]);
    }

    public function test_buy_crypto_successfully()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/trades/buy', [
                'crypto_symbol' => 'btc',
                'amount' => 0.001,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'trade_id',
                'type',
                'crypto',
                'crypto_amount',
                'rate',
                'subtotal',
                'fee',
                'total_cost',
                'fee_percent',
                'timestamp',
                'new_balance'
            ]
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('buy', $response->json('data.type'));
    }

    public function test_buy_crypto_insufficient_balance()
    {
        $this->wallet->update(['naira_balance' => 100]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/trades/buy', [
                'crypto_symbol' => 'btc',
                'amount' => 0.001,
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['success', 'message']);
        $this->assertFalse($response->json('success'));
    }

    public function test_sell_crypto_successfully()
    {
        // First, create a crypto holding for the user
        CryptoHolding::create([
            'wallet_id' => $this->wallet->id,
            'crypto_symbol' => 'btc',
            'amount' => 0.5,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/trades/sell', [
                'crypto_symbol' => 'btc',
                'amount' => 0.1,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'trade_id',
                'type',
                'crypto',
                'crypto_amount',
                'rate',
                'gross_proceeds',
                'fee',
                'net_proceeds',
                'fee_percent',
                'timestamp',
                'new_balance'
            ]
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('sell', $response->json('data.type'));
    }

    public function test_sell_crypto_insufficient_holdings()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/trades/sell', [
                'crypto_symbol' => 'btc',
                'amount' => 0.1,
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['success', 'message']);
        $this->assertFalse($response->json('success'));
    }

    public function test_get_trade_history()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/trades/history');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'pagination' => ['total', 'per_page', 'current_page', 'last_page']
        ]);
    }

    public function test_get_trade_history_with_filters()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/trades/history?symbol=btc&type=buy&page=1&per_page=10');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'pagination'
        ]);
    }

    public function test_buy_crypto_without_authentication()
    {
        $response = $this->postJson('/api/trades/buy', [
            'crypto_symbol' => 'btc',
            'amount' => 0.001,
        ]);

        $response->assertStatus(401);
    }

    public function test_access_rates_without_authentication()
    {
        // Rates endpoint should be public
        $response = $this->getJson('/api/trades/rates');
        $response->assertStatus(200);
    }

    public function test_buy_invalid_crypto_symbol()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/trades/buy', [
                'crypto_symbol' => 'invalid_coin',
                'amount' => 0.001,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('crypto_symbol');
    }

    public function test_sell_invalid_crypto_symbol()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/trades/sell', [
                'crypto_symbol' => 'doge',
                'amount' => 0.001,
            ]);

        $response->assertStatus(422);
    }

    public function test_buy_minimum_amount_validation()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/trades/buy', [
                'crypto_symbol' => 'btc',
                'amount' => 0.00005, // Below BTC minimum
            ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    public function test_sell_exact_holdings_amount()
    {
        $holdingAmount = 0.5;
        CryptoHolding::create([
            'wallet_id' => $this->wallet->id,
            'crypto_symbol' => 'eth',
            'amount' => $holdingAmount,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/trades/sell', [
                'crypto_symbol' => 'eth',
                'amount' => $holdingAmount,
            ]);

        $response->assertStatus(201);
        $this->assertTrue($response->json('success'));

        // Verify holding is depleted
        $holding = CryptoHolding::where('wallet_id', $this->wallet->id)
            ->where('crypto_symbol', 'eth')
            ->first();

        $this->assertEquals(0, $holding->amount);
    }

    public function test_sell_more_than_holdings()
    {
        CryptoHolding::create([
            'wallet_id' => $this->wallet->id,
            'crypto_symbol' => 'usdt',
            'amount' => 50,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/trades/sell', [
                'crypto_symbol' => 'usdt',
                'amount' => 100, // More than available
            ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
    }

    public function test_buy_fee_calculation()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/trades/buy', [
                'crypto_symbol' => 'btc',
                'amount' => 0.001,
            ]);

        $data = $response->json('data');
        $subtotal = $data['subtotal'];
        $fee = $data['fee'];
        $totalCost = $data['total_cost'];

        // Verify fee is 2% of subtotal
        $expectedFee = $subtotal * 0.02;
        $this->assertAlmostEquals($expectedFee, $fee, 2);

        // Verify total = subtotal + fee
        $this->assertAlmostEquals($subtotal + $fee, $totalCost, 2);
    }

    public function test_sell_fee_deduction()
    {
        CryptoHolding::create([
            'wallet_id' => $this->wallet->id,
            'crypto_symbol' => 'btc',
            'amount' => 1.0,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/trades/sell', [
                'crypto_symbol' => 'btc',
                'amount' => 0.1,
            ]);

        $data = $response->json('data');
        $grossProceeds = $data['gross_proceeds'];
        $fee = $data['fee'];
        $netProceeds = $data['net_proceeds'];

        // Verify fee is 2% of gross proceeds
        $expectedFee = $grossProceeds * 0.02;
        $this->assertAlmostEquals($expectedFee, $fee, 2);

        // Verify net = gross - fee
        $this->assertAlmostEquals($grossProceeds - $fee, $netProceeds, 2);
    }

    public function test_buy_creates_holding_if_not_exists()
    {
        $this->actingAs($this->user)
            ->postJson('/api/trades/buy', [
                'crypto_symbol' => 'eth',
                'amount' => 0.1,
            ]);

        $holding = CryptoHolding::where('wallet_id', $this->wallet->id)
            ->where('crypto_symbol', 'eth')
            ->first();

        $this->assertNotNull($holding);
        $this->assertEquals(0.1, $holding->amount);
    }

    public function test_buy_increments_existing_holding()
    {
        CryptoHolding::create([
            'wallet_id' => $this->wallet->id,
            'crypto_symbol' => 'eth',
            'amount' => 0.5,
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/trades/buy', [
                'crypto_symbol' => 'eth',
                'amount' => 0.1,
            ]);

        $holding = CryptoHolding::where('wallet_id', $this->wallet->id)
            ->where('crypto_symbol', 'eth')
            ->first();

        $this->assertEquals(0.6, $holding->amount);
    }

    public function test_multiple_trades_history()
    {
        // Make multiple trades
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->user)
                ->postJson('/api/trades/buy', [
                    'crypto_symbol' => 'btc',
                    'amount' => 0.001,
                ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/trades/history');

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('pagination.total'));
    }

    public function test_trade_history_filter_by_symbol()
    {
        // Make different trades
        $this->actingAs($this->user)->postJson('/api/trades/buy', [
            'crypto_symbol' => 'btc',
            'amount' => 0.001,
        ]);

        $this->actingAs($this->user)->postJson('/api/trades/buy', [
            'crypto_symbol' => 'eth',
            'amount' => 0.01,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/trades/history?symbol=btc');

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('btc', strtolower($data[0]['crypto_symbol']));
    }

    public function test_trade_history_filter_by_type()
    {
        CryptoHolding::create([
            'wallet_id' => $this->wallet->id,
            'crypto_symbol' => 'btc',
            'amount' => 1.0,
        ]);

        // Buy trade
        $this->actingAs($this->user)->postJson('/api/trades/buy', [
            'crypto_symbol' => 'eth',
            'amount' => 0.1,
        ]);

        // Sell trade
        $this->actingAs($this->user)->postJson('/api/trades/sell', [
            'crypto_symbol' => 'btc',
            'amount' => 0.1,
        ]);

        // Filter for sell only
        $response = $this->actingAs($this->user)
            ->getJson('/api/trades/history?type=sell');

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('sell', $data[0]['type']);
    }

    public function test_wallet_balance_decreases_after_buy()
    {
        $initialBalance = $this->wallet->naira_balance;

        $this->actingAs($this->user)
            ->postJson('/api/trades/buy', [
                'crypto_symbol' => 'btc',
                'amount' => 0.001,
            ]);

        $this->wallet->refresh();
        $this->assertLessThan($initialBalance, $this->wallet->naira_balance);
    }

    public function test_wallet_balance_increases_after_sell()
    {
        $initialBalance = $this->wallet->naira_balance;

        CryptoHolding::create([
            'wallet_id' => $this->wallet->id,
            'crypto_symbol' => 'btc',
            'amount' => 1.0,
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/trades/sell', [
                'crypto_symbol' => 'btc',
                'amount' => 0.1,
            ]);

        $this->wallet->refresh();
        $this->assertGreaterThan($initialBalance, $this->wallet->naira_balance);
    }

    public function test_trade_status_completed()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/trades/buy', [
                'crypto_symbol' => 'usdt',
                'amount' => 10,
            ]);

        $tradeId = $response->json('data.trade_id');
        $trade = \App\Models\Trade::find($tradeId);

        $this->assertEquals('completed', $trade->status);
    }

    public function test_sell_crypto_without_authentication()
    {
        $response = $this->postJson('/api/trades/sell', [
            'crypto_symbol' => 'btc',
            'amount' => 0.1,
        ]);

        $response->assertStatus(401);
    }

    public function test_trade_history_without_authentication()
    {
        $response = $this->getJson('/api/trades/history');

        $response->assertStatus(401);
    }

    public function test_buy_all_supported_cryptos()
    {
        $cryptos = ['btc', 'eth', 'usdt'];
        $amounts = ['btc' => 0.001, 'eth' => 0.01, 'usdt' => 10];

        foreach ($cryptos as $crypto) {
            $response = $this->actingAs($this->user)
                ->postJson('/api/trades/buy', [
                    'crypto_symbol' => $crypto,
                    'amount' => $amounts[$crypto],
                ]);

            $response->assertStatus(201);
            $this->assertTrue($response->json('success'));
        }
    }

    protected function assertAlmostEquals($expected, $actual, $precision = 2)
    {
        $this->assertEqualsWithDelta($expected, $actual, 0.01, "Expected {$expected}, got {$actual}");
    }
}