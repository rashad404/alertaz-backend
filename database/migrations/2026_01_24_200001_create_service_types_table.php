<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('key', 50);
            $table->json('label');  // {"az": "Hostinq", "en": "Hosting", "ru": "Хостинг"}
            $table->string('icon', 50)->nullable();
            $table->string('user_link_field', 50)->default('phone');  // how services link to customers
            $table->json('fields');  // field definitions
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->unique(['client_id', 'key']);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};
