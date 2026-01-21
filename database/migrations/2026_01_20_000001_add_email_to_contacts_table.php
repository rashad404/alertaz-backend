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
        Schema::table('contacts', function (Blueprint $table) {
            // Add email column after phone
            $table->string('email', 255)->nullable()->after('phone');

            // Make phone nullable (currently required)
            $table->string('phone', 20)->nullable()->change();

            // Add index on email for faster lookups
            $table->index('email');
        });

        // Add unique constraint for client_id + email (only where email is not null)
        // MySQL/MariaDB doesn't support partial unique indexes directly,
        // so we use a unique index and handle null duplicates at application level
        Schema::table('contacts', function (Blueprint $table) {
            $table->unique(['client_id', 'email'], 'contacts_client_id_email_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique('contacts_client_id_email_unique');
            $table->dropIndex(['email']);
            $table->dropColumn('email');

            // Make phone required again
            $table->string('phone', 20)->nullable(false)->change();
        });
    }
};
