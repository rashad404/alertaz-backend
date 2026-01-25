<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 255)->nullable();  // partner's ID for this customer
            $table->string('phone', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('name', 255)->nullable();
            $table->json('data')->nullable();  // extra attributes (birthday, company, etc.)
            $table->timestamps();

            // Unique constraints - at least one identifier must be unique per client
            $table->unique(['client_id', 'phone']);
            $table->unique(['client_id', 'email']);
            $table->unique(['client_id', 'external_id']);

            $table->index('client_id');
            $table->index('phone');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
