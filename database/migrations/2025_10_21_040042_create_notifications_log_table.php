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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // email, sms, push, telegram, whatsapp
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable(); // Additional data for the notification
            $table->boolean('is_mock')->default(false); // Was this a mock notification?
            $table->boolean('is_read')->default(false); // Has user read this notification?
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'is_read']); // For filtering unread notifications
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
