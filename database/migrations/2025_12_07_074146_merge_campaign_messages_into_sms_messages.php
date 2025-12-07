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
        // Step 1: Add new columns to sms_messages for campaign support
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->enum('source', ['api', 'campaign'])->default('api')->after('user_id');
            $table->unsignedBigInteger('client_id')->nullable()->after('source');
            $table->unsignedBigInteger('campaign_id')->nullable()->after('client_id');
            $table->unsignedBigInteger('contact_id')->nullable()->after('campaign_id');

            // Foreign keys
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('campaign_id')->references('id')->on('campaigns')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');

            // Indexes
            $table->index('source');
            $table->index('client_id');
            $table->index('campaign_id');
        });

        // Step 2: Migrate existing campaign_messages data into sms_messages
        DB::statement("
            INSERT INTO sms_messages
                (user_id, source, client_id, campaign_id, contact_id, phone, message, sender, cost, status,
                 provider_transaction_id, delivery_status_code, error_message, sent_at, delivered_at, ip_address, created_at, updated_at)
            SELECT
                camp.created_by,
                'campaign',
                camp.client_id,
                cm.campaign_id,
                cm.contact_id,
                cm.phone,
                cm.message,
                cm.sender,
                cm.cost,
                cm.status,
                cm.provider_transaction_id,
                cm.delivery_status_code,
                cm.error_message,
                cm.sent_at,
                cm.delivered_at,
                NULL,
                cm.created_at,
                cm.updated_at
            FROM campaign_messages cm
            JOIN campaigns camp ON cm.campaign_id = camp.id
        ");

        // Step 3: Drop the old campaign_messages table
        Schema::dropIfExists('campaign_messages');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Recreate campaign_messages table
        Schema::create('campaign_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->string('phone', 20);
            $table->text('message')->comment('Final rendered message');
            $table->string('sender', 50);
            $table->decimal('cost', 10, 2);

            $table->enum('status', ['pending', 'sent', 'delivered', 'failed'])->default('pending');
            $table->string('provider_transaction_id')->nullable();
            $table->integer('delivery_status_code')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->index('campaign_id');
            $table->index('contact_id');
            $table->index('status');
            $table->index('provider_transaction_id');
        });

        // Step 2: Move campaign messages back
        DB::statement("
            INSERT INTO campaign_messages
                (campaign_id, contact_id, phone, message, sender, cost, status,
                 provider_transaction_id, delivery_status_code, error_message, sent_at, delivered_at, created_at, updated_at)
            SELECT
                campaign_id, contact_id, phone, message, sender, cost, status,
                provider_transaction_id, delivery_status_code, error_message, sent_at, delivered_at, created_at, updated_at
            FROM sms_messages
            WHERE source = 'campaign'
        ");

        // Step 3: Delete campaign messages from sms_messages
        DB::statement("DELETE FROM sms_messages WHERE source = 'campaign'");

        // Step 4: Remove the new columns from sms_messages
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropForeign(['campaign_id']);
            $table->dropForeign(['contact_id']);
            $table->dropIndex(['source']);
            $table->dropIndex(['client_id']);
            $table->dropIndex(['campaign_id']);
            $table->dropColumn(['source', 'client_id', 'campaign_id', 'contact_id']);
        });
    }
};
