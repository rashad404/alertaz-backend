<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->enum('channel', ['sms', 'email', 'both'])->default('sms');
            $table->text('message_template')->nullable();  // SMS template
            $table->string('email_subject', 255)->nullable();
            $table->text('email_body')->nullable();
            $table->json('variables')->nullable();  // list of variables used
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
