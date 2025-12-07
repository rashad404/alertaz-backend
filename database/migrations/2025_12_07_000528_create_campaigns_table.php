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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('name')->comment('Campaign name');
            $table->string('sender', 50)->comment('SMS sender (e.g., Sayt.az)');
            $table->text('message_template')->comment('Template with {{variables}}');
            $table->enum('status', ['draft', 'scheduled', 'sending', 'completed', 'cancelled', 'failed'])->default('draft');
            $table->json('segment_filter')->comment('Filter conditions for segment');
            $table->timestamp('scheduled_at')->nullable()->comment('When to send (NULL = send now)');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Stats
            $table->unsignedInteger('target_count')->default(0)->comment('Matched contacts count');
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->decimal('total_cost', 10, 2)->default(0.00);

            // Metadata
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade')->comment('User who created campaign');
            $table->boolean('is_test')->default(false)->comment('Test/mock campaign');

            $table->timestamps();

            $table->index('client_id');
            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
