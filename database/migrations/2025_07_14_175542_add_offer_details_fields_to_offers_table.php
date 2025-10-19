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
            $table->json('loan_type')->nullable();
            $table->integer('max_duration')->nullable();
            $table->decimal('min_interest_rate', 5, 2)->nullable();
            $table->decimal('max_interest_rate', 5, 2)->nullable();
            $table->boolean('employment_reference_required')->default(false);
            $table->boolean('guarantor_required')->default(false);
            $table->boolean('collateral_required')->default(false);
            $table->json('note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            //
        });
    }
};
