<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A partner's customer identity is its external_id (the partner's own user id).
 * Phone/email are attributes, not identities: two different customers can legitimately
 * share a phone (family member, second account, placeholder number). Enforcing
 * UNIQUE(client_id, phone) silently dropped the colliding customer and orphaned its
 * services (no customer -> no expiry notification). Identity stays on external_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_client_id_phone_unique');
            $table->dropUnique('customers_client_id_email_unique');
            // keep customers_client_id_external_id_unique as the identity
            // keep the plain phone/email indexes for lookups
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unique(['client_id', 'phone']);
            $table->unique(['client_id', 'email']);
        });
    }
};
