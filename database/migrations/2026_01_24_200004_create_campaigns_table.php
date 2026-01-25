<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);

            // Target configuration
            $table->enum('target_type', ['customer', 'service'])->default('customer');
            $table->foreignId('service_type_id')->nullable()->constrained('service_types')->nullOnDelete();

            // Channel configuration
            $table->enum('channel', ['sms', 'email', 'both'])->default('sms');
            $table->string('sender', 50)->nullable();  // SMS sender ID
            $table->string('email_sender', 255)->nullable();  // email address
            $table->string('email_display_name', 255)->nullable();

            // Message templates
            $table->text('message_template')->nullable();  // SMS template
            $table->string('email_subject', 255)->nullable();
            $table->text('email_body')->nullable();

            // Segment filter
            $table->json('filter')->nullable();  // segment conditions

            // Status and type
            $table->enum('status', ['draft', 'scheduled', 'sending', 'completed', 'active', 'paused', 'cancelled', 'failed'])->default('draft');
            $table->enum('campaign_type', ['one_time', 'automated'])->default('one_time');

            // Scheduling
            $table->dateTime('scheduled_at')->nullable();
            $table->integer('check_interval_minutes')->nullable();  // for automated
            $table->integer('cooldown_days')->default(30);
            $table->tinyInteger('run_start_hour')->nullable();  // 0-23
            $table->tinyInteger('run_end_hour')->nullable();  // 0-23
            $table->dateTime('next_run_at')->nullable();
            $table->dateTime('last_run_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->integer('run_count')->default(0);

            // Execution timestamps
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            // Stats
            $table->integer('target_count')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);

            // Email-specific stats
            $table->integer('email_sent_count')->default(0);
            $table->integer('email_delivered_count')->default(0);
            $table->integer('email_failed_count')->default(0);
            $table->decimal('email_total_cost', 10, 2)->default(0);

            // Balance warnings
            $table->boolean('balance_warning_sent')->default(false);
            $table->string('pause_reason', 255)->nullable();

            // Metadata
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_test')->default(false);
            $table->timestamps();

            // Indexes
            $table->index(['client_id', 'status']);
            $table->index('next_run_at');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
