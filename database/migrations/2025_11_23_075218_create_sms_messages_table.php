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
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('phone', 20);
            $table->text('message');
            $table->string('sender', 50)->default('Alert.az');
            $table->decimal('cost', 10, 2);
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed'])->default('pending');
            $table->string('provider_transaction_id')->nullable();
            $table->integer('delivery_status_code')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('phone');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
