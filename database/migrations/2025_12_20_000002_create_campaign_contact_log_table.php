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
        Schema::create('campaign_contact_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->timestamp('sent_at');

            // Unique constraint to prevent duplicates
            // When cooldown expires, we update the sent_at instead of creating new record
            $table->unique(['campaign_id', 'contact_id'], 'unique_campaign_contact');

            // Index for efficient cooldown queries
            $table->index(['campaign_id', 'sent_at'], 'idx_campaign_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_contact_log');
    }
};
