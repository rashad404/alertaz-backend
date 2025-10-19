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
        Schema::create('contact_info', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->default('KREDIT.AZ');
            $table->string('legal_name')->nullable();
            $table->string('voen')->nullable();
            $table->json('chief_editor')->nullable(); // Translatable
            $table->string('domain_owner')->nullable();
            $table->json('address'); // Translatable
            $table->string('phone');
            $table->string('phone_2')->nullable();
            $table->string('email');
            $table->string('email_2')->nullable();
            $table->json('working_hours'); // Translatable
            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('map_embed_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_info');
    }
};