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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('icon')->nullable();
            $table->string('bank_name');
            $table->json('title');
            $table->decimal('annual_interest_rate', 5, 2)->nullable();
            $table->string('duration')->nullable();
            $table->decimal('monthly_payment', 10, 2)->nullable();
            $table->decimal('maximum_credit_limit', 15, 2)->nullable();
            $table->foreignId('category_id')->constrained('offers_categories')->onDelete('cascade');
            $table->integer('order')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
}; 