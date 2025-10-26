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
        Schema::create('stock_prices', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 10)->unique()->index(); // AAPL, GOOGL, etc.
            $table->string('name'); // Apple Inc., Alphabet Inc., etc.
            $table->string('exchange', 20)->default('NASDAQ'); // NYSE, NASDAQ

            // Prices in USD
            $table->decimal('current_price', 16, 4);
            $table->decimal('open', 16, 4)->nullable();
            $table->decimal('high', 16, 4)->nullable();
            $table->decimal('low', 16, 4)->nullable();
            $table->decimal('previous_close', 16, 4)->nullable();

            // Changes
            $table->decimal('change', 16, 4)->nullable();
            $table->decimal('change_percent', 10, 4)->nullable();

            // Volume and market data
            $table->bigInteger('volume')->nullable();
            $table->decimal('market_cap', 20, 2)->nullable();
            $table->integer('market_cap_rank')->nullable();

            // 52-week range
            $table->decimal('fifty_two_week_high', 16, 4)->nullable();
            $table->decimal('fifty_two_week_low', 16, 4)->nullable();

            // Timestamps
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('market_cap_rank');
            $table->index('last_updated');
            $table->index(['exchange', 'market_cap_rank']); // Compound index for filtering
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_prices');
    }
};
