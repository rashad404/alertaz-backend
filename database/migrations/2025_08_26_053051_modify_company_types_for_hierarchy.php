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
        // First drop the unique index on type_name
        Schema::table('company_types', function (Blueprint $table) {
            $table->dropUnique(['type_name']);
        });
        
        // Then modify the type_name column to support JSON translations
        Schema::table('company_types', function (Blueprint $table) {
            $table->text('type_name')->change();
            $table->text('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_types', function (Blueprint $table) {
            $table->string('type_name', 100)->unique()->change();
            $table->text('description')->nullable()->change();
        });
    }
};