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
        Schema::table('offers', function (Blueprint $table) {
            // Drop the old foreign key constraint to banks table
            $table->dropForeign(['bank_id']);
            
            // Add new foreign key constraint to companies table
            $table->foreign('bank_id')->references('id')->on('companies')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            // Drop the foreign key to companies
            $table->dropForeign(['bank_id']);
            
            // Re-add foreign key to banks table
            $table->foreign('bank_id')->references('id')->on('banks')->onDelete('set null');
        });
    }
};