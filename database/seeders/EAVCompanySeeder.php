<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EAVCompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get company types
        $bankTypeId = 1; // Banks (from CompanyTypeHierarchySeeder)
        $insuranceTypeId = 2; // Insurance Companies (from CompanyTypeHierarchySeeder)
        $creditOrgTypeId = 3; // Credit Organizations (from CompanyTypeHierarchySeeder)
        
        // Sample Banks
        $banks = [
            [
                'name' => 'Kapital Bank',
                'slug' => 'kapital-bank',
                'attributes' => [
                    'swift_code' => 'AIIBAZ2X',
                    'bank_code' => '200028',
                    'voen' => '1500031691',
                    'phone' => '+994 12 196',
                    'email' => 'info@kapitalbank.az',
                    'website' => 'https://kapitalbank.az',
                    'about' => json_encode([
                        'az' => 'Kapital Bank Azərbaycanın aparıcı banklarından biridir.',
                        'en' => 'Kapital Bank is one of the leading banks in Azerbaijan.',
                        'ru' => 'Kapital Bank является одним из ведущих банков Азербайджана.'
                    ]),
                ]
            ],
            [
                'name' => 'Pasha Bank',
                'slug' => 'pasha-bank',
                'attributes' => [
                    'swift_code' => 'PASAAZ22',
                    'bank_code' => '505201',
                    'voen' => '1401555071',
                    'phone' => '+994 12 496 50 00',
                    'email' => 'info@pashabank.az',
                    'website' => 'https://pashabank.az',
                    'about' => json_encode([
                        'az' => 'PASHA Bank Azərbaycanın aparıcı korporativ bankıdır.',
                        'en' => 'PASHA Bank is Azerbaijan\'s leading corporate bank.',
                        'ru' => 'PASHA Bank является ведущим корпоративным банком Азербайджана.'
                    ]),
                ]
            ],
            [
                'name' => 'ABB',
                'slug' => 'abb',
                'attributes' => [
                    'swift_code' => 'IBAZAZ2X',
                    'bank_code' => '805620',
                    'voen' => '9900001881',
                    'phone' => '+994 12 493 00 91',
                    'email' => 'info@abb-bank.az',
                    'website' => 'https://abb-bank.az',
                    'about' => json_encode([
                        'az' => 'ABB - Azərbaycan Beynəlxalq Bankı, müasir bank xidmətləri təklif edir.',
                        'en' => 'ABB - Azerbaijan International Bank offers modern banking services.',
                        'ru' => 'ABB - Международный Банк Азербайджана предлагает современные банковские услуги.'
                    ]),
                ]
            ]
        ];
        
        // Sample Insurance Companies
        $insuranceCompanies = [
            [
                'name' => 'Pasha Sığorta',
                'slug' => 'pasha-sigorta',
                'attributes' => [
                    'license_number' => 'MF-022',
                    'phone' => '+994 12 598 17 17',
                    'email' => 'info@pasha-insurance.az',
                    'website' => 'https://pasha-insurance.az',
                    'about' => json_encode([
                        'az' => 'Pasha Sığorta Azərbaycanın lider sığorta şirkətidir.',
                        'en' => 'Pasha Insurance is Azerbaijan\'s leading insurance company.',
                        'ru' => 'Pasha Insurance является ведущей страховой компанией Азербайджана.'
                    ]),
                ]
            ],
            [
                'name' => 'AXA MBASK',
                'slug' => 'axa-mbask',
                'attributes' => [
                    'license_number' => 'MF-033',
                    'phone' => '+994 12 497 77 77',
                    'email' => 'info@axa-mbask.az',
                    'website' => 'https://axa.az',
                    'about' => json_encode([
                        'az' => 'AXA MBASK beynəlxalq standartlarda sığorta xidmətləri təklif edir.',
                        'en' => 'AXA MBASK offers insurance services at international standards.',
                        'ru' => 'AXA MBASK предлагает страховые услуги на международном уровне.'
                    ]),
                ]
            ]
        ];
        
        // Sample Credit Organizations
        $creditOrgs = [
            [
                'name' => 'TBC Kredit',
                'slug' => 'tbc-kredit',
                'attributes' => [
                    'phone' => '+994 12 599 89 89',
                    'email' => 'info@tbckredit.az',
                    'website' => 'https://tbckredit.az',
                    'about' => json_encode([
                        'az' => 'TBC Kredit bank olmayan kredit təşkilatıdır.',
                        'en' => 'TBC Credit is a non-bank credit organization.',
                        'ru' => 'TBC Кредит является небанковской кредитной организацией.'
                    ]),
                ]
            ],
            [
                'name' => 'FinEx',
                'slug' => 'finex',
                'attributes' => [
                    'phone' => '+994 12 404 44 44',
                    'email' => 'info@finex.az',
                    'website' => 'https://finex.az',
                    'about' => json_encode([
                        'az' => 'FinEx sürətli kredit xidmətləri təklif edir.',
                        'en' => 'FinEx offers fast credit services.',
                        'ru' => 'FinEx предлагает быстрые кредитные услуги.'
                    ]),
                ]
            ]
        ];
        
        // Insert Banks
        foreach ($banks as $bank) {
            $companyId = $this->createCompany($bank['name'], $bank['slug'], $bankTypeId);
            $this->addCompanyAttributes($companyId, $bankTypeId, $bank['attributes']);
            
            // Add sample branches
            $this->addBankBranches($companyId);
            
            // Add sample deposits
            $this->addBankDeposits($companyId);
            
            // Add sample credit cards
            $this->addBankCreditCards($companyId);
        }
        
        // Insert Insurance Companies
        foreach ($insuranceCompanies as $insurance) {
            $companyId = $this->createCompany($insurance['name'], $insurance['slug'], $insuranceTypeId);
            $this->addCompanyAttributes($companyId, $insuranceTypeId, $insurance['attributes']);
        }
        
        // Insert Credit Organizations
        foreach ($creditOrgs as $creditOrg) {
            $companyId = $this->createCompany($creditOrg['name'], $creditOrg['slug'], $creditOrgTypeId);
            $this->addCompanyAttributes($companyId, $creditOrgTypeId, $creditOrg['attributes']);
        }
        
        echo "EAV Company data seeded successfully!\n";
    }
    
    private function createCompany(string $name, string $slug, int $typeId): int
    {
        return DB::table('companies')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'company_type_id' => $typeId,
            'is_active' => true,
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    private function addCompanyAttributes(int $companyId, int $typeId, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            // Get attribute definition
            $attrDef = DB::table('company_attribute_definitions')
                ->where('company_type_id', $typeId)
                ->where('attribute_key', $key)
                ->first();
            
            if (!$attrDef) {
                continue;
            }
            
            $data = [
                'company_id' => $companyId,
                'attribute_definition_id' => $attrDef->id,
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            // Store value in appropriate column based on data type
            switch ($attrDef->data_type) {
                case 'string':
                case 'text':
                    $data['value_text'] = $value;
                    break;
                case 'number':
                case 'decimal':
                    $data['value_number'] = $value;
                    break;
                case 'date':
                    $data['value_date'] = $value;
                    break;
                case 'json':
                    $data['value_json'] = is_string($value) ? $value : json_encode($value);
                    break;
                case 'boolean':
                    $data['value_number'] = $value ? 1 : 0;
                    break;
            }
            
            DB::table('company_attribute_values')->insert($data);
        }
    }
    
    private function addBankBranches(int $bankId): void
    {
        $branchTypeId = DB::table('company_entity_types')
            ->where('entity_name', 'branch')
            ->value('id');
        
        if (!$branchTypeId) {
            return;
        }
        
        $branches = [
            ['name' => 'Main Branch', 'code' => 'HQ001'],
            ['name' => 'City Center Branch', 'code' => 'CC002'],
            ['name' => 'Airport Branch', 'code' => 'AP003']
        ];
        
        foreach ($branches as $branch) {
            $entityId = DB::table('company_entities')->insertGetId([
                'company_id' => $bankId,
                'entity_type_id' => $branchTypeId,
                'entity_name' => $branch['name'],
                'entity_code' => $branch['code'],
                'is_active' => true,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Add branch attributes
            $this->addEntityAttribute($entityId, $branchTypeId, 'address', json_encode([
                'az' => 'Bakı şəhəri, Nəsimi rayonu',
                'en' => 'Baku city, Nasimi district',
                'ru' => 'г. Баку, Насиминский район'
            ]));
            $this->addEntityAttribute($entityId, $branchTypeId, 'phone', '+994 12 493 00 91');
            $this->addEntityAttribute($entityId, $branchTypeId, 'branch_code', $branch['code']);
            $this->addEntityAttribute($entityId, $branchTypeId, 'working_hours', json_encode([
                'weekdays' => '09:00-18:00',
                'saturday' => '10:00-15:00',
                'sunday' => 'closed'
            ]));
        }
    }
    
    private function addBankDeposits(int $bankId): void
    {
        $depositTypeId = DB::table('company_entity_types')
            ->where('entity_name', 'deposit')
            ->value('id');
        
        if (!$depositTypeId) {
            return;
        }
        
        $deposits = [
            ['name' => 'Standart Əmanət', 'rate' => 8.5, 'min' => 100],
            ['name' => 'Premium Əmanət', 'rate' => 10.5, 'min' => 5000],
            ['name' => 'VIP Əmanət', 'rate' => 12.0, 'min' => 50000]
        ];
        
        foreach ($deposits as $deposit) {
            $entityId = DB::table('company_entities')->insertGetId([
                'company_id' => $bankId,
                'entity_type_id' => $depositTypeId,
                'entity_name' => $deposit['name'],
                'is_active' => true,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Add deposit attributes
            $this->addEntityAttribute($entityId, $depositTypeId, 'deposit_name', json_encode([
                'az' => $deposit['name'],
                'en' => str_replace('Əmanət', 'Deposit', $deposit['name']),
                'ru' => str_replace('Əmanət', 'Депозит', $deposit['name'])
            ]));
            $this->addEntityAttribute($entityId, $depositTypeId, 'interest_rate', $deposit['rate']);
            $this->addEntityAttribute($entityId, $depositTypeId, 'minimum_amount', $deposit['min']);
            $this->addEntityAttribute($entityId, $depositTypeId, 'term_months', 12);
        }
    }
    
    private function addBankCreditCards(int $bankId): void
    {
        $cardTypeId = DB::table('company_entity_types')
            ->where('entity_name', 'credit_card')
            ->value('id');
        
        if (!$cardTypeId) {
            return;
        }
        
        $cards = [
            ['name' => 'Standard Card', 'type' => 'Visa', 'fee' => 30, 'cashback' => 1.0],
            ['name' => 'Gold Card', 'type' => 'Mastercard', 'fee' => 60, 'cashback' => 2.0],
            ['name' => 'Platinum Card', 'type' => 'Visa', 'fee' => 120, 'cashback' => 3.0]
        ];
        
        foreach ($cards as $card) {
            $entityId = DB::table('company_entities')->insertGetId([
                'company_id' => $bankId,
                'entity_type_id' => $cardTypeId,
                'entity_name' => $card['name'],
                'is_active' => true,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Add credit card attributes
            $this->addEntityAttribute($entityId, $cardTypeId, 'card_name', json_encode([
                'az' => $card['name'],
                'en' => $card['name'],
                'ru' => $card['name']
            ]));
            $this->addEntityAttribute($entityId, $cardTypeId, 'card_type', $card['type']);
            $this->addEntityAttribute($entityId, $cardTypeId, 'annual_fee', $card['fee']);
            $this->addEntityAttribute($entityId, $cardTypeId, 'cashback_rate', $card['cashback']);
        }
    }
    
    private function addEntityAttribute($entityId, $entityTypeId, $key, $value): void
    {
        $attrDef = DB::table('company_attribute_definitions')
            ->where('entity_type_id', $entityTypeId)
            ->where('attribute_key', $key)
            ->first();
        
        if (!$attrDef) {
            return;
        }
        
        $data = [
            'entity_id' => $entityId,
            'attribute_definition_id' => $attrDef->id,
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        // Store value in appropriate column based on data type
        switch ($attrDef->data_type) {
            case 'string':
            case 'text':
                $data['value_text'] = $value;
                break;
            case 'number':
            case 'decimal':
                $data['value_number'] = $value;
                break;
            case 'date':
                $data['value_date'] = $value;
                break;
            case 'json':
                $data['value_json'] = is_string($value) ? $value : json_encode($value);
                break;
            case 'boolean':
                $data['value_number'] = $value ? 1 : 0;
                break;
        }
        
        DB::table('company_entity_attribute_values')->insert($data);
    }
}