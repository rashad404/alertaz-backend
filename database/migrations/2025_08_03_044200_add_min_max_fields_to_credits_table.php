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
            $table->decimal('min_amount', 10, 2)->nullable()->after('credit_amount');
            $table->decimal('max_amount', 10, 2)->nullable()->after('min_amount');
            $table->integer('min_term_months')->nullable()->after('credit_term');
            $table->integer('max_term_months')->nullable()->after('min_term_months');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('interest_rate');
            $table->string('bank_phone')->nullable()->after('bank_name');
            $table->text('bank_address')->nullable()->after('bank_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credits', function (Blueprint $table) {
            $table->dropColumn([
                'min_amount',
                'max_amount',
                'min_term_months',
                'max_term_months',
                'commission_rate',
                'bank_phone',
                'bank_address'
            ]);
        });
    }
};