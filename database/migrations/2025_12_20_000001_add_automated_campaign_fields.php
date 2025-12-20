<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, alter the status enum to include new statuses for automated campaigns
        DB::statement("ALTER TABLE campaigns MODIFY COLUMN status ENUM('draft', 'scheduled', 'sending', 'completed', 'cancelled', 'failed', 'active', 'paused') DEFAULT 'draft'");

        Schema::table('campaigns', function (Blueprint $table) {
            // Campaign type: one_time (existing) vs automated (new triggered campaigns)
            $table->enum('type', ['one_time', 'automated'])->default('one_time')->after('status');

            // How often to check/run automated campaign (in minutes)
            // e.g., 1 = every minute, 60 = hourly, 1440 = daily
            $table->unsignedInteger('check_interval_minutes')->nullable()->after('type');

            // Don't resend to same contact within X days (deduplication)
            $table->unsignedInteger('cooldown_days')->default(30)->after('check_interval_minutes');

            // Optional end date for automated campaigns
            $table->timestamp('ends_at')->nullable()->after('cooldown_days');

            // Track last execution time
            $table->timestamp('last_run_at')->nullable()->after('ends_at');

            // Scheduled next run time
            $table->timestamp('next_run_at')->nullable()->after('last_run_at');

            // Total runs count for automated campaigns
            $table->unsignedInteger('run_count')->default(0)->after('next_run_at');

            // Index for scheduler queries
            $table->index(['type', 'status', 'next_run_at'], 'idx_automated_scheduler');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex('idx_automated_scheduler');
            $table->dropColumn([
                'type',
                'check_interval_minutes',
                'cooldown_days',
                'ends_at',
                'last_run_at',
                'next_run_at',
                'run_count',
            ]);
        });

        // Revert status enum
        DB::statement("ALTER TABLE campaigns MODIFY COLUMN status ENUM('draft', 'scheduled', 'sending', 'completed', 'cancelled', 'failed') DEFAULT 'draft'");
    }
};
