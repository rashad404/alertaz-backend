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
        Schema::table('campaigns', function (Blueprint $table) {
            $table->tinyInteger('run_start_hour')->nullable()->after('ends_at'); // 0-23, default work hours start
            $table->tinyInteger('run_end_hour')->nullable()->after('run_start_hour'); // 0-23, default work hours end
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['run_start_hour', 'run_end_hour']);
        });
    }
};
