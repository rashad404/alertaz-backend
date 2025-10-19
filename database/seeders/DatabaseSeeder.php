<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Core configuration seeders
        $this->call([
            LanguageSeeder::class,
            CompanyTypeHierarchySeeder::class, // New hierarchical company types with subcategories
            CategorySeeder::class,
            OffersCategorySeeder::class,
            OffersDurationSeeder::class,
            CurrencySeeder::class,
        ]);

        // Bank and Company seeders - Using new EAV system
        $this->call([
            ComprehensiveEAVSeeder::class, // Comprehensive EAV seeder with all company data
        ]);

        // Financial products seeders - Updated for new EAV system
        $this->call([
            // NewOfferSeeder::class, // New offer seeder compatible with EAV companies
            // DepositOfferSeeder::class, // Needs updating for new company system
            CreditTypeSeeder::class,  // Must run before CreditSeeder
            // CreditSeeder::class, // Needs updating for new company system
            // RecommendedBankSeeder::class, // Needs updating for new company system
        ]);

        // Insurance seeders - Disabled for new EAV system
        // $this->call([
        //     InsuranceSeeder::class,
        // ]);

        // Content seeders
        $this->call([
            MenuSeeder::class,
            NewsSeeder::class,
            HomeSliderNewsSeeder::class,
            HeroBannerSeeder::class,
            HomePageAdSeeder::class,
            BlogSeeder::class,
            FaqSeeder::class,
            PageContentSeeder::class,
            MetaTagSeeder::class,
            ContactInfoSeeder::class,
            GuideSeeder::class,
        ]);

        // About page seeders
        $this->call([
            AboutModuleSeeder::class,
            AboutPageDataSeeder::class,
            AboutPageDynamicDataSeeder::class,
            OurMissionSeeder::class,
        ]);

        // Exchange rates - Disabled for new EAV system
        // $this->call([
        //     BuySellRatesSeeder::class, // Depends on companies
        // ]);

        // Admin and user seeders
        $this->call([
            AdminSeeder::class,
        ]);

        // Create test user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}