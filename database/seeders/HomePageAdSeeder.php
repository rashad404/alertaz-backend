<?php

namespace Database\Seeders;

use App\Models\HomePageAd;
use Illuminate\Database\Seeder;

class HomePageAdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ads = [
            [
                'image' => '/finance-ad-banner.svg',
                'url' => '/credits',
                'place' => 'hero_section',
                'is_active' => true,
                'order' => 1,
            ],
            [
                'image' => '/credit-card-banner.svg',
                'url' => '/credits/cards',
                'place' => 'sidebar',
                'is_active' => true,
                'order' => 2,
            ],
            [
                'image' => '/quick-credit-banner.svg',
                'url' => '/credits',
                'place' => 'footer',
                'is_active' => true,
                'order' => 3,
            ],
        ];

        foreach ($ads as $ad) {
            HomePageAd::updateOrCreate(
                ['place' => $ad['place'], 'order' => $ad['order']],
                $ad
            );
        }

        $this->command->info('Home page ads seeded successfully!');
    }
}