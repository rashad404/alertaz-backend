<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crypto_prices', function (Blueprint $table) {
            $table->id();
            $table->string('coin_id')->index(); // bitcoin, ethereum, etc.
            $table->string('symbol', 10); // btc, eth, etc.
            $table->string('name'); // Bitcoin, Ethereum, etc.
            $table->string('image')->nullable();

            // Prices in USD
            $table->decimal('current_price', 20, 8);
            $table->decimal('market_cap', 20, 2);
            $table->integer('market_cap_rank')->nullable();
            $table->decimal('total_volume', 20, 2);
            $table->decimal('high_24h', 20, 8)->nullable();
            $table->decimal('low_24h', 20, 8)->nullable();
            $table->decimal('price_change_24h', 20, 8)->nullable();
            $table->decimal('price_change_percentage_24h', 10, 4)->nullable();
            $table->decimal('price_change_percentage_1h', 10, 4)->nullable();
            $table->decimal('price_change_percentage_7d', 10, 4)->nullable();
            $table->decimal('price_change_percentage_30d', 10, 4)->nullable();

            // Supply data
            $table->decimal('circulating_supply', 20, 2)->nullable();
            $table->decimal('total_supply', 20, 2)->nullable();
            $table->decimal('max_supply', 20, 2)->nullable();

            // Sparkline data (JSON array of prices)
            $table->json('sparkline_7d')->nullable();

            // Popular in Azerbaijan flag
            $table->boolean('popular_in_azerbaijan')->default(false);

            // Timestamps
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('symbol');
            $table->index('market_cap_rank');
            $table->index('popular_in_azerbaijan');
            $table->index('last_updated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crypto_prices');
    }
};
