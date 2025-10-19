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
        // Fix double-encoded JSON names
        $companies = DB::table('companies')->get();
        
        foreach ($companies as $company) {
            $name = $company->name;
            
            // Check if it's double-encoded (starts with {" but not {"az")
            if (is_string($name) && str_starts_with($name, '{"') && !str_starts_with($name, '{"az"')) {
                // It's likely double-encoded, try to decode
                try {
                    $decoded = json_decode($name, true);
                    if ($decoded && isset($decoded['en']) && is_string($decoded['en']) && str_starts_with($decoded['en'], '{')) {
                        // Double encoded confirmed - the 'en' field contains JSON
                        $actualName = json_decode($decoded['en'], true);
                        if ($actualName) {
                            DB::table('companies')
                                ->where('id', $company->id)
                                ->update(['name' => json_encode($actualName)]);
                        }
                    }
                } catch (\Exception $e) {
                    // Skip if can't decode
                }
            }
        }
        
        // Now ensure all companies have proper JSON names based on slug
        $nameMappings = [
            'kapital-bank' => ['az' => 'Kapital Bank', 'en' => 'Kapital Bank', 'ru' => 'Капитал Банк'],
            'pasha-bank' => ['az' => 'PAŞA Bank', 'en' => 'PASHA Bank', 'ru' => 'ПАША Банк'],
            'abb' => ['az' => 'ABB - Azərbaycan Beynəlxalq Bankı', 'en' => 'ABB - Azerbaijan International Bank', 'ru' => 'МБА - Международный Банк Азербайджана'],
            'unibank' => ['az' => 'Unibank', 'en' => 'Unibank', 'ru' => 'Юнибанк'],
            'bank-respublika' => ['az' => 'Bank Respublika', 'en' => 'Bank Respublika', 'ru' => 'Банк Республика'],
            'tbc-kredit' => ['az' => 'TBC Kredit', 'en' => 'TBC Credit', 'ru' => 'TBC Кредит'],
            'finex' => ['az' => 'FinEx', 'en' => 'FinEx', 'ru' => 'ФинЭкс'],
        ];
        
        foreach ($nameMappings as $slug => $names) {
            DB::table('companies')
                ->where('slug', $slug)
                ->update(['name' => json_encode($names)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Can't really reverse this
    }
};