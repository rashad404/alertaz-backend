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
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->json('title'); // Translatable field
            $table->string('slug')->unique();
            $table->string('url')->nullable(); // Can be null for parent menus with dropdowns
            $table->unsignedBigInteger('parent_id')->nullable(); // For submenu items
            $table->integer('position')->default(0); // Display order
            $table->enum('target', ['_self', '_blank'])->default('_self'); // Link target
            $table->boolean('has_dropdown')->default(false); // Whether menu has children
            $table->boolean('is_active')->default(true); // Active status
            $table->enum('menu_location', ['header', 'footer', 'both'])->default('header'); // Where to show menu
            $table->string('icon')->nullable(); // Optional icon class
            $table->json('meta')->nullable(); // Additional metadata if needed
            $table->timestamps();
            
            // Add foreign key for parent_id
            $table->foreign('parent_id')->references('id')->on('menus')->onDelete('cascade');
            
            // Add indexes
            $table->index('slug');
            $table->index('position');
            $table->index('parent_id');
            $table->index('is_active');
            $table->index('menu_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
