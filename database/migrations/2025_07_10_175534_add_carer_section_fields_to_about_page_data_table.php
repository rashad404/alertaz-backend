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
        Schema::table('about_page_data', function (Blueprint $table) {
            $table->json('carer_section_title')->nullable()->after('our_mission_text');
            $table->string('carer_section_image')->nullable()->after('carer_section_title');
            $table->json('carer_section_image_alt_text')->nullable()->after('carer_section_image');
            $table->json('carer_section_desc')->nullable()->after('carer_section_image_alt_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('about_page_data', function (Blueprint $table) {
            $table->dropColumn([
                'carer_section_title',
                'carer_section_image',
                'carer_section_image_alt_text',
                'carer_section_desc',
            ]);
        });
    }
};
