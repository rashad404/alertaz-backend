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
            $table->json('mission_section_title')->nullable()->after('image_alt_text');
            $table->string('video_image')->nullable()->after('mission_section_title');
            $table->string('video_link')->nullable()->after('video_image');
            $table->json('our_mission_title')->nullable()->after('video_link');
            $table->json('our_mission_text')->nullable()->after('our_mission_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('about_page_data', function (Blueprint $table) {
            $table->dropColumn([
                'mission_section_title',
                'video_image',
                'video_link',
                'our_mission_title',
                'our_mission_text',
            ]);
        });
    }
};
