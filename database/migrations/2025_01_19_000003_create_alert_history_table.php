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
        Schema::create('alert_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_alert_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->json('triggered_conditions'); // The conditions that were met
            $table->json('current_values'); // The actual values at trigger time
            $table->json('notification_channels'); // Which channels were notified
            $table->json('delivery_status'); // Status per channel
            $table->text('message')->nullable(); // The message sent
            $table->timestamp('triggered_at');
            $table->timestamps();

            $table->index(['personal_alert_id', 'triggered_at']);
            $table->index(['user_id', 'triggered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_history');
    }
};