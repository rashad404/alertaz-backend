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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 3);
            $table->string('currency_name')->nullable();
            $table->decimal('rate', 10, 4);
            $table->decimal('nominal', 10, 2)->default(1); // For currencies like RUB (100 RUB)
            $table->date('rate_date');
            $table->string('source')->default('CBAR');
            $table->timestamps();
            
            $table->index(['currency_code', 'rate_date']);
            $table->unique(['currency_code', 'rate_date', 'source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};