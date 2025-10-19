<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AlignCreditProductsSeeder extends Seeder
{
    /**
     * Align credit products (entities) with company type subcategories
     */
    public function run(): void
    {
        // Mapping of entity loan types to company_type subcategories
        $mappings = [
            // Map entity names to subcategory slugs
            'Online Loan' => 'microloans',           // Online loans are typically microloans
            'Onlayn Kredit' => 'microloans',
            'Student Loan' => 'education-loans',     // Student loans -> Education loans
            'Tələbə Krediti' => 'education-loans',
            'Pawn Loan' => 'pawnshop-loans',        // Pawn loans -> Pawnshop loans
            'Lombard Krediti' => 'pawnshop-loans',
            'Cash Loan' => 'cash-loans',            // Cash loans
            'Nağd Kredit' => 'cash-loans',
            'Business Credit' => 'business-loans',   // Business credit
            'Biznes Krediti' => 'business-loans',
            'Auto Loan' => 'auto-loans',            // Auto loans
            'Avtomobil Krediti' => 'auto-loans',
            'Personal Loan' => 'cash-loans',        // Personal loans are cash loans
            'Şəxsi Kredit' => 'cash-loans',
            'Consumer Credit' => 'cash-loans',      // Consumer credit is cash loan
            'İstehlak Krediti' => 'cash-loans',
        ];
        
        // Update entity names to be consistent with subcategories
        $entityUpdates = [
            ['old_en' => 'Online Loan', 'new' => ['az' => 'Mikrokreditlər', 'en' => 'Microloans', 'ru' => 'Микрокредиты']],
            ['old_en' => 'Student Loan', 'new' => ['az' => 'Təhsil Kreditləri', 'en' => 'Education Loans', 'ru' => 'Образовательные кредиты']],
            ['old_en' => 'Pawn Loan', 'new' => ['az' => 'Lombard Kreditləri', 'en' => 'Pawnshop Loans', 'ru' => 'Ломбардные кредиты']],
            ['old_en' => 'Cash Loan', 'new' => ['az' => 'Nağd Kreditlər', 'en' => 'Cash Loans', 'ru' => 'Наличные кредиты']],
            ['old_en' => 'Business Credit', 'new' => ['az' => 'Biznes Kreditləri', 'en' => 'Business Loans', 'ru' => 'Бизнес-кредиты']],
            ['old_en' => 'Personal Loan', 'new' => ['az' => 'Nağd Kreditlər', 'en' => 'Cash Loans', 'ru' => 'Наличные кредиты']],
            ['old_en' => 'Consumer Credit', 'new' => ['az' => 'Nağd Kreditlər', 'en' => 'Cash Loans', 'ru' => 'Наличные кредиты']],
        ];
        
        // Update entity names - entity_name column contains the name
        foreach ($entityUpdates as $update) {
            // Find entities with the old English name (entity_name is stored as JSON)
            $entities = DB::table('company_entities')
                ->whereRaw("JSON_VALID(entity_name) AND JSON_UNQUOTE(JSON_EXTRACT(entity_name, '$.en')) = ?", [$update['old_en']])
                ->get();
            
            foreach ($entities as $entity) {
                DB::table('company_entities')
                    ->where('id', $entity->id)
                    ->update([
                        'entity_name' => json_encode($update['new']),
                        'updated_at' => now()
                    ]);
                    
                echo "Updated entity {$entity->id}: {$update['old_en']} -> {$update['new']['en']}\n";
            }
        }
        
        // Add metadata to company_entity_types to link with subcategories
        $creditOrgTypeId = 3; // Credit Organizations
        $loanEntityType = DB::table('company_entity_types')
            ->where('parent_company_type_id', $creditOrgTypeId)
            ->where('entity_name', 'credit_loan')
            ->first();
            
        if ($loanEntityType) {
            // Store the mapping in the description field as JSON
            DB::table('company_entity_types')
                ->where('id', $loanEntityType->id)
                ->update([
                    'description' => json_encode([
                        'az' => 'Kredit məhsulları',
                        'en' => 'Loan products',
                        'ru' => 'Кредитные продукты',
                        'subcategory_mappings' => $mappings
                    ]),
                    'updated_at' => now()
                ]);
                
            echo "Updated loan entity type with subcategory mappings\n";
        }
        
        // Create a mapping table for better querying (optional, for future use)
        $this->createMappingTable();
        
        echo "Credit products alignment completed successfully!\n";
    }
    
    private function createMappingTable(): void
    {
        // Check if the table already exists
        if (!DB::getSchemaBuilder()->hasTable('entity_subcategory_mappings')) {
            DB::statement("
                CREATE TABLE entity_subcategory_mappings (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    entity_name VARCHAR(255),
                    subcategory_slug VARCHAR(255),
                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL,
                    INDEX idx_entity_name (entity_name),
                    INDEX idx_subcategory_slug (subcategory_slug)
                )
            ");
            
            // Insert mappings
            $mappings = [
                ['Microloans', 'microloans'],
                ['Mikrokreditlər', 'microloans'],
                ['Education Loans', 'education-loans'],
                ['Təhsil Kreditləri', 'education-loans'],
                ['Pawnshop Loans', 'pawnshop-loans'],
                ['Lombard Kreditləri', 'pawnshop-loans'],
                ['Cash Loans', 'cash-loans'],
                ['Nağd Kreditlər', 'cash-loans'],
                ['Business Loans', 'business-loans'],
                ['Biznes Kreditləri', 'business-loans'],
                ['Auto Loans', 'auto-loans'],
                ['Avtomobil Kreditləri', 'auto-loans'],
                ['Mortgage Loans', 'mortgage-loans'],
                ['İpoteka Kreditləri', 'mortgage-loans'],
                ['Credit Lines', 'credit-lines'],
                ['Kredit Xətləri', 'credit-lines'],
            ];
            
            foreach ($mappings as $mapping) {
                DB::table('entity_subcategory_mappings')->insert([
                    'entity_name' => $mapping[0],
                    'subcategory_slug' => $mapping[1],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            echo "Created entity_subcategory_mappings table\n";
        }
    }
}