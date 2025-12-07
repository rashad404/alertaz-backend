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
        Schema::create('client_attribute_schemas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('attribute_key', 100)->comment('e.g., hosting_expiry');
            $table->enum('attribute_type', ['string', 'number', 'integer', 'date', 'boolean', 'enum', 'array']);
            $table->string('label')->comment('UI display label');
            $table->json('options')->nullable()->comment('For enum type: ["Basic", "Premium"]');
            $table->string('item_type', 50)->nullable()->comment('For array type: object, string, number');
            $table->json('properties')->nullable()->comment('For array of objects: nested schema');
            $table->boolean('required')->default(false);
            $table->json('metadata')->nullable()->comment('Additional settings (format, validation, etc.)');
            $table->timestamps();

            $table->unique(['client_id', 'attribute_key']);
            $table->index('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_attribute_schemas');
    }
};
