<?php

namespace Database\Seeders;

use App\Models\HomeSliderNews;
use App\Models\News;
use Illuminate\Database\Seeder;

class HomeSliderNewsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing slider news
        HomeSliderNews::truncate();

        // Get the 5 most recent news items with images
        $sliderNews = News::whereNotNull('thumbnail_image')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Add them to the slider with order
        foreach ($sliderNews as $index => $news) {
            HomeSliderNews::create([
                'news_id' => $news->id,
                'order' => $index + 1,
            ]);
        }

        echo "Added " . $sliderNews->count() . " news items to home slider.\n";
    }
}