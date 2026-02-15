<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;
use App\Models\CryptoHolding;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WalletTest extends TestCase
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
            'naira_balance' => 100000,
        ]);
    }

    public function test_get_wallet_balance()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/wallet/balance');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'naira_balance',
            'holdings'
        ]);
        $response->assertJson([
            'naira_balance' => '100000.00000000'
        ]);
    }

    public function test_get_wallet_balance_with_holdings()
    {
        CryptoHolding::create([
            'wallet_id' => $this->wallet->id,
            'crypto_symbol' => 'btc',
            'amount' => 0.5,
        ]);

        CryptoHolding::create([
            'wallet_id' => $this->wallet->id,
            'crypto_symbol' => 'eth',
            'amount' => 2.0,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/wallet/balance');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('holdings'));
    }

    public function test_add_funds_successfully()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/wallet/add-funds', [
                'amount' => 50000
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Funds added successfully',
            'balance' => '150000.00000000'
        ]);

        $this->wallet->refresh();
        $this->assertEquals(150000, $this->wallet->naira_balance);
    }

    public function test_add_funds_minimum_validation()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/wallet/add-funds', [
                'amount' => 50 // Below minimum
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('amount');
    }

    public function test_add_funds_invalid_amount()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/wallet/add-funds', [
                'amount' => 'invalid'
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('amount');
    }

    public function test_add_funds_without_funds_key()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/wallet/add-funds', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('amount');
    }

    public function test_get_transactions()
    {
        // Add some transactions
        $this->user->transactions()->create([
            'type' => 'deposit',
            'amount' => 50000,
            'description' => 'Test deposit',
            'previous_balance' => 100000,
            'new_balance' => 150000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/wallet/transactions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'pagination' => [
                'total',
                'per_page',
                'current_page',
                'last_page'
            ]
        ]);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_get_transactions_with_pagination()
    {
        // Create multiple transactions
        for ($i = 0; $i < 25; $i++) {
            $this->user->transactions()->create([
                'type' => 'deposit',
                'amount' => 1000,
                'description' => "Deposit {$i}",
                'previous_balance' => $i * 1000,
                'new_balance' => ($i + 1) * 1000,
            ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/wallet/transactions?page=1&per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(25, $response->json('pagination.total'));
        $this->assertEquals(3, $response->json('pagination.last_page'));
    }

    public function test_get_transactions_page_two()
    {
        // Create multiple transactions
        for ($i = 0; $i < 25; $i++) {
            $this->user->transactions()->create([
                'type' => 'deposit',
                'amount' => 1000,
                'description' => "Deposit {$i}",
                'previous_balance' => $i * 1000,
                'new_balance' => ($i + 1) * 1000,
            ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/wallet/transactions?page=2&per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(2, $response->json('pagination.current_page'));
    }

    public function test_wallet_requires_authentication()
    {
        $response = $this->getJson('/api/wallet/balance');

        $response->assertStatus(401);
    }

    public function test_add_funds_requires_authentication()
    {
        $response = $this->postJson('/api/wallet/add-funds', [
            'amount' => 50000
        ]);

        $response->assertStatus(401);
    }

    public function test_transactions_history_requires_authentication()
    {
        $response = $this->getJson('/api/wallet/transactions');

        $response->assertStatus(401);
    }

    public function test_multiple_users_isolated_wallets()
    {
        $user2 = User::factory()->create();
        Wallet::create([
            'user_id' => $user2->id,
            'naira_balance' => 200000,
        ]);

        $response1 = $this->actingAs($this->user)
            ->getJson('/api/wallet/balance');

        $response2 = $this->actingAs($user2)
            ->getJson('/api/wallet/balance');

        // Each user should see only their own balance
        $this->assertEquals('100000.00000000', $response1->json('naira_balance'));
        $this->assertEquals('200000.00000000', $response2->json('naira_balance'));
    }

    public function test_add_large_amount()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/wallet/add-funds', [
                'amount' => 999999999.99
            ]);

        $response->assertStatus(200);
        $this->wallet->refresh();
        $this->assertGreaterThan(900000000, $this->wallet->naira_balance);
    }

    public function test_transaction_history_ordered_latest_first()
    {
        // Create transactions on different days
        $this->user->transactions()->create([
            'type' => 'deposit',
            'amount' => 1000,
            'description' => 'First deposit',
            'previous_balance' => 0,
            'new_balance' => 1000,
            'created_at' => now()->subDays(5),
        ]);

        $this->user->transactions()->create([
            'type' => 'deposit',
            'amount' => 2000,
            'description' => 'Second deposit',
            'previous_balance' => 1000,
            'new_balance' => 3000,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/wallet/transactions');

        $data = $response->json('data');
        // Most recent should be first
        $this->assertEquals('Second deposit', $data[0]['description']);
        $this->assertEquals('First deposit', $data[1]['description']);
    }
}
