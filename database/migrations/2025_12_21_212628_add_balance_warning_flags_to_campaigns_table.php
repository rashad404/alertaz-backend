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
        Schema::table('campaigns', function (Blueprint $table) {
            $table->boolean('balance_warning_20_sent')->default(false);
            $table->boolean('balance_warning_10_sent')->default(false);
            $table->boolean('balance_warning_5_sent')->default(false);
            $table->string('pause_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['balance_warning_20_sent', 'balance_warning_10_sent', 'balance_warning_5_sent', 'pause_reason']);
        });
    }
};
