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
}


