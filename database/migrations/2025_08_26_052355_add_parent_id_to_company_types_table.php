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
        Schema::table('company_types', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            $table->string('slug', 100)->nullable()->after('type_name');
            $table->integer('display_order')->default(0)->after('slug');
            $table->boolean('is_active')->default(true)->after('display_order');
            
            $table->foreign('parent_id')->references('id')->on('company_types')->onDelete('cascade');
            $table->index('parent_id');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_types', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['slug']);
            $table->dropColumn(['parent_id', 'slug', 'display_order', 'is_active']);
        });
    }
};
