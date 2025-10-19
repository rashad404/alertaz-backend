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
        Schema::create('personal_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('alert_type_id')->constrained()->onDelete('cascade');
            $table->string('name'); // User-defined name: "BTC > 100k"
            $table->string('asset')->nullable(); // BTC, ETH, AAPL, etc.
            $table->json('conditions'); // {field: "price", operator: ">", value: 100000}
            $table->json('notification_channels'); // ["telegram", "email", "push"]
            $table->integer('check_frequency')->default(300); // seconds
            $table->boolean('is_active')->default(true);
            $table->boolean('is_recurring')->default(true); // One-time or recurring
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->integer('trigger_count')->default(0);
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index('alert_type_id');
            $table->index('last_checked_at');
            $table->index('asset');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_alerts');
    }
};