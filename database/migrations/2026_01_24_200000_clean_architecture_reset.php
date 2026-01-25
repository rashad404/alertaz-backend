<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clean architecture reset - drops old campaign-related tables
     */
    public function up(): void
    {
        // Drop old tables that will be replaced
        Schema::dropIfExists('campaign_contact_log');
        Schema::dropIfExists('email_messages');
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('saved_segments');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('client_attribute_schemas');
        // Keep clients table but we'll update it
    }

    public function down(): void
    {
        // Cannot restore - this is a one-way migration
    }
};
