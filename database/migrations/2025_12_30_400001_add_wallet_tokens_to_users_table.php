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
            $table->text('wallet_access_token')->nullable()->after('wallet_id');
            $table->text('wallet_refresh_token')->nullable()->after('wallet_access_token');
            $table->timestamp('wallet_token_expires_at')->nullable()->after('wallet_refresh_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wallet_access_token', 'wallet_refresh_token', 'wallet_token_expires_at']);
        });
    }
};
