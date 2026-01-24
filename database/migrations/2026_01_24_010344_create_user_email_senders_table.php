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
        Schema::create('user_email_senders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('email')->comment('Sender email address');
            $table->string('name')->comment('Sender display name');
            $table->boolean('is_verified')->default(false)->comment('Whether domain/email is verified in SES');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false)->comment('Available to all users');
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'email']);
        });

        // Insert default email sender
        DB::table('user_email_senders')->insert([
            'user_id' => null,
            'email' => 'noreply@alert.az',
            'name' => 'Alert.az',
            'is_verified' => true,
            'is_active' => true,
            'is_default' => true,
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add email_sender column to campaigns table
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('email_sender')->nullable()->after('sender')->comment('Email sender address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('email_sender');
        });

        Schema::dropIfExists('user_email_senders');
    }
};
