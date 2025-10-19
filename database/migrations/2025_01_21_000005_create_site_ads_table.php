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
        Schema::create('site_ads', function (Blueprint $table) {
            $table->id();
            $table->text('iframe')->nullable();
            $table->string('image')->nullable();
            $table->string('url')->nullable();
            $table->enum('place', ['up', 'bottom'])->default('up');
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_ads');
    }
}; 