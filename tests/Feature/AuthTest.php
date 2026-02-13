<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;
use RefreshDatabase; 

class AuthTest extends TestCase
{
    public function test_user_can_register()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['user', 'token']);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $this->assertDatabaseHas('wallets', ['user_id' => 1]);
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('password123'),
        ]);

        Wallet::create(['user_id' => $user->id, 'naira_balance' => 0]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['user', 'token']);
    }

    public function test_user_can_logout()
    {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'naira_balance' => 0]);

        $response = $this->actingAs($user)->postJson('/api/auth/logout');

        $response->assertStatus(200);
    }

    public function test_get_profile()
    {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'naira_balance' => 0]);

        $response = $this->actingAs($user)->getJson('/api/auth/profile');

        $response->assertStatus(200);
        $response->assertJson(['id' => $user->id]);
    }
}
