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
        Schema::table('meta_tags', function (Blueprint $table) {
            $table->string('seo_keywords')->change();
            $table->text('seo_description')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meta_tags', function (Blueprint $table) {
            $table->text('seo_keywords')->change();
            $table->longText('seo_description')->change();
        });
    }
};
