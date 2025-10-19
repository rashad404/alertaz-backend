<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AboutModuleSeeder extends Seeder
{
    /**
     * Run the database seeds for the entire About module.
     * This seeder orchestrates all About-related seeders.
     */
    public function run(): void
    {
        $this->command->info('Starting About Module seeding...');
        
        // Seed About Page main data
        $this->call(AboutPageDataSeeder::class);
        
        // Seed Our Missions
        $this->call(OurMissionSeeder::class);
        
        // Seed Dynamic Sections
        $this->call(AboutPageDynamicDataSeeder::class);
        
        $this->command->info('About Module seeding completed successfully!');
    }
}