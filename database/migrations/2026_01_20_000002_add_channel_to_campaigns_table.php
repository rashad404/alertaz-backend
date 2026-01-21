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
            // Add channel field after message_template
            $table->enum('channel', ['sms', 'email', 'both'])->default('sms')->after('message_template');

            // Add email template fields
            $table->text('email_subject_template')->nullable()->after('channel');
            $table->text('email_body_template')->nullable()->after('email_subject_template');

            // Add email stats
            $table->unsignedInteger('email_sent_count')->default(0)->after('failed_count');
            $table->unsignedInteger('email_delivered_count')->default(0)->after('email_sent_count');
            $table->unsignedInteger('email_failed_count')->default(0)->after('email_delivered_count');
            $table->decimal('email_total_cost', 10, 2)->default(0.00)->after('email_failed_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'channel',
                'email_subject_template',
                'email_body_template',
                'email_sent_count',
                'email_delivered_count',
                'email_failed_count',
                'email_total_cost',
            ]);
        });
    }
};
