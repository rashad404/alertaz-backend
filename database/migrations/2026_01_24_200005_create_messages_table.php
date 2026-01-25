<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();

            // Channel and recipient
            $table->enum('channel', ['sms', 'email']);
            $table->string('recipient', 255);  // phone or email

            // Content
            $table->text('content');  // message body
            $table->string('subject', 255)->nullable();  // for email

            // Sender info
            $table->string('sender', 255)->nullable();

            // Status tracking
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'bounced', 'opened', 'clicked'])->default('pending');
            $table->string('provider_message_id', 255)->nullable();  // external ID from SMS/email provider
            $table->text('error_message')->nullable();
            $table->integer('error_code')->nullable();

            // Cost tracking
            $table->decimal('cost', 10, 4)->default(0);
            $table->tinyInteger('segments')->default(1);  // SMS segments

            // Timestamps
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('clicked_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('campaign_id');
            $table->index('customer_id');
            $table->index('service_id');
            $table->index(['client_id', 'channel']);
            $table->index('status');
            $table->index('sent_at');
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
