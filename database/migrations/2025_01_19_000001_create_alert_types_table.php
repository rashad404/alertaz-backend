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
        Schema::create('alert_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // crypto, weather, website, stock, currency
            $table->json('name'); // Translatable name
            $table->json('description')->nullable();
            $table->string('icon')->nullable();
            $table->json('configuration_schema'); // JSON schema for settings
            $table->json('condition_fields'); // Available fields for conditions
            $table->string('data_source')->nullable(); // api_endpoint, scraper, etc
            $table->integer('check_interval')->default(300); // seconds
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('slug');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_types');
    }
};