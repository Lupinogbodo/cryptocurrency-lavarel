<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;
use RefreshDatabase; 

class TradeTest extends TestCase
{
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

    public function test_get_crypto_rates()
    {
        $response = $this->getJson('/api/trades/rates');

        $response->assertStatus(200);
        $response->assertJsonStructure(['rates']);
    }
}
