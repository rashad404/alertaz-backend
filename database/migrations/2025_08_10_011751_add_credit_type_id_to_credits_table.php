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
        Schema::table('credits', function (Blueprint $table) {
            // Add credit_type_id column after id
            $table->unsignedBigInteger('credit_type_id')->nullable()->after('id');
            
            // Add foreign key constraint
            $table->foreign('credit_type_id')->references('id')->on('credit_types')->onDelete('set null');
            
            // Add index for better query performance
            $table->index('credit_type_id');
        });
        
        // After adding the column, we'll migrate the data in the seeder
        // Then we can drop the old credit_type column
        Schema::table('credits', function (Blueprint $table) {
            $table->dropColumn('credit_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            // Add back the JSON credit_type column
            $table->json('credit_type')->nullable();
            
            // Drop the foreign key and column
            $table->dropForeign(['credit_type_id']);
            $table->dropIndex(['credit_type_id']);
            $table->dropColumn('credit_type_id');
        });
    }
};