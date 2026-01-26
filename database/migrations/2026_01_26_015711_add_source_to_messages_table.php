<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Source: customer, service, api, campaign
            $table->string('source', 50)->nullable()->after('is_test');
        });

        // Set source based on existing data
        DB::statement("UPDATE messages SET source = 'campaign' WHERE campaign_id IS NOT NULL");
        DB::statement("UPDATE messages SET source = 'service' WHERE campaign_id IS NULL AND service_id IS NOT NULL");
        DB::statement("UPDATE messages SET source = 'customer' WHERE campaign_id IS NULL AND service_id IS NULL AND customer_id IS NOT NULL");
        DB::statement("UPDATE messages SET source = 'api' WHERE source IS NULL");
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
