<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooldowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->enum('target_type', ['customer', 'service']);
            $table->unsignedBigInteger('target_id');  // customer_id or service_id
            $table->dateTime('sent_at');

            // Unique constraint to prevent duplicates
            $table->unique(['campaign_id', 'target_type', 'target_id'], 'cooldowns_campaign_target_unique');

            // Index for lookups
            $table->index(['client_id', 'campaign_id', 'target_type', 'target_id'], 'cooldowns_lookup_idx');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooldowns');
    }
};
