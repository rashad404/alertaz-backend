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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->unique()->after('email');
            $table->string('google_id')->nullable()->unique();
            $table->string('facebook_id')->nullable()->unique();
            $table->string('avatar')->nullable();
            $table->string('provider')->nullable(); // google, facebook, phone, email
            $table->string('provider_id')->nullable();
            $table->timestamp('phone_verified_at')->nullable();

            // Make email nullable for phone-only users
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();

            // Contact info for notifications
            $table->string('telegram_chat_id')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('slack_webhook')->nullable();
            $table->string('push_token')->nullable();

            // Preferences
            $table->json('notification_preferences')->nullable();
            $table->string('timezone')->default('Asia/Baku');
            $table->string('locale')->default('az');

            $table->index('phone');
            $table->index('provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'google_id',
                'facebook_id',
                'avatar',
                'provider',
                'provider_id',
                'phone_verified_at',
                'telegram_chat_id',
                'whatsapp_number',
                'slack_webhook',
                'push_token',
                'notification_preferences',
                'timezone',
                'locale'
            ]);

            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};