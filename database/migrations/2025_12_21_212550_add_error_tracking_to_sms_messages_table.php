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
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->integer('error_code')->nullable()->after('error_message');
            $table->integer('retry_count')->default(0)->after('error_code');
            $table->timestamp('last_retry_at')->nullable()->after('retry_count');
            $table->string('failure_reason')->nullable()->after('last_retry_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->dropColumn(['error_code', 'retry_count', 'last_retry_at', 'failure_reason']);
        });
    }
};
