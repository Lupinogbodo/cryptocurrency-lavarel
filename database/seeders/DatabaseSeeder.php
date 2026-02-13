<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Vincent',
            'email' => 'lupinogbodo@outlook.com',
            'password' => bcrypt('teststaff123'),
        ]);

        Wallet::create([
            'user_id' => $user->id,
            'naira_balance' => 1000000,
        ]);
    }
}

