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
        Schema::table('otp_verifications', function (Blueprint $table) {
            // Add type column if it doesn't exist
            if (!Schema::hasColumn('otp_verifications', 'type')) {
                $table->enum('type', ['sms', 'email'])->default('sms')->after('id');
            }

            // Add email column if it doesn't exist
            if (!Schema::hasColumn('otp_verifications', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }

            // Make phone nullable since we can have email verifications
            if (Schema::hasColumn('otp_verifications', 'phone')) {
                $table->string('phone')->nullable()->change();
            }
        });

        // Add indexes in separate statements to handle existing indexes
        // Skip the expires_at index as it already exists from the original migration
        try {
            Schema::table('otp_verifications', function (Blueprint $table) {
                $table->index(['type', 'phone'], 'otp_verifications_type_phone_index');
            });
        } catch (\Exception $e) {
            // Index already exists
        }

        try {
            Schema::table('otp_verifications', function (Blueprint $table) {
                $table->index(['type', 'email'], 'otp_verifications_type_email_index');
            });
        } catch (\Exception $e) {
            // Index already exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('otp_verifications', function (Blueprint $table) {
            // Drop indexes if they exist
            try {
                $table->dropIndex('otp_verifications_type_phone_index');
            } catch (\Exception $e) {
                // Index doesn't exist
            }

            try {
                $table->dropIndex('otp_verifications_type_email_index');
            } catch (\Exception $e) {
                // Index doesn't exist
            }

            try {
                $table->dropIndex('otp_verifications_expires_at_index');
            } catch (\Exception $e) {
                // Index doesn't exist
            }

            // Drop columns
            if (Schema::hasColumn('otp_verifications', 'type')) {
                $table->dropColumn('type');
            }

            if (Schema::hasColumn('otp_verifications', 'email')) {
                $table->dropColumn('email');
            }

            // Make phone required again
            if (Schema::hasColumn('otp_verifications', 'phone')) {
                $table->string('phone')->nullable(false)->change();
            }
        });
    }
};