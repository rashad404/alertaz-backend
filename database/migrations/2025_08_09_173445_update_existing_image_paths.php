<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update news table thumbnail_image paths
        $newsItems = DB::table('news')->whereNotNull('thumbnail_image')->get();
        foreach ($newsItems as $news) {
            if ($news->thumbnail_image && !str_starts_with($news->thumbnail_image, 'news/')) {
                // If it's just a filename, update it to be in the proper folder structure
                $filename = basename($news->thumbnail_image);
                if ($filename === $news->thumbnail_image) {
                    DB::table('news')->where('id', $news->id)->update([
                        'thumbnail_image' => 'news/' . now()->format('Y/m') . '/' . $filename
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert news table thumbnail_image paths
        $newsItems = DB::table('news')->whereNotNull('thumbnail_image')->get();
        foreach ($newsItems as $news) {
            if ($news->thumbnail_image && str_starts_with($news->thumbnail_image, 'news/')) {
                // Extract just the filename
                $filename = basename($news->thumbnail_image);
                DB::table('news')->where('id', $news->id)->update([
                    'thumbnail_image' => $filename
                ]);
            }
        }
    }
};