<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AboutPageDynamicData;

class AboutPageDynamicDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data in development/staging environments
        if (app()->environment(['local', 'staging'])) {
            AboutPageDynamicData::truncate();
        }

        // No dynamic sections needed - all removed as requested

        $this->command->info('About page dynamic data seeded successfully!');
    }
}