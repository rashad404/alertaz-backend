<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained('service_types')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('external_id', 255)->nullable();  // partner's ID for this service
            $table->string('name', 255);  // primary identifier (domain name, etc.)
            $table->date('expiry_at')->nullable();  // indexed for date filters
            $table->string('status', 50)->nullable();  // 'active', 'suspended', 'expired'
            $table->json('data')->nullable();  // all other fields (flexible)
            $table->timestamps();

            // External ID must be unique per client + type
            $table->unique(['client_id', 'service_type_id', 'external_id']);

            // Indexes for common queries
            $table->index(['client_id', 'service_type_id']);
            $table->index('expiry_at');
            $table->index('status');
            $table->index('customer_id');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
