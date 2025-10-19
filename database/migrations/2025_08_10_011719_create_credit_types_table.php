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
        Schema::create('credit_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // e.g., 'cash', 'mortgage', 'auto', 'business'
            $table->json('name'); // {"az": "Nağd kredit", "en": "Cash loan", "ru": "Наличный кредит"}
            $table->json('description')->nullable(); // Optional description in multiple languages
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_types');
    }
};