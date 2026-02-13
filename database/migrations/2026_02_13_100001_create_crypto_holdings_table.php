<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crypto_holdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('crypto_symbol');
            $table->decimal('amount', 18, 8)->default(0);
            $table->timestamps();
            $table->unique(['wallet_id', 'crypto_symbol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_holdings');
    }
};
