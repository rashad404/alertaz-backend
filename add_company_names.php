<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Company names mapping
$companyNames = [
    'kapital-bank' => ['az' => 'Kapital Bank', 'en' => 'Kapital Bank', 'ru' => 'Капитал Банк'],
    'pasha-bank' => ['az' => 'PASHA Bank', 'en' => 'PASHA Bank', 'ru' => 'ПАША Банк'],
    'abb' => ['az' => 'ABB', 'en' => 'ABB', 'ru' => 'АББ'],
    'pasha-sigorta' => ['az' => 'PASHA Sığorta', 'en' => 'PASHA Insurance', 'ru' => 'ПАША Страхование'],
    'axa-mbask' => ['az' => 'AXA MBASK', 'en' => 'AXA MBASK', 'ru' => 'AXA MBASK'],
    'tbc-kredit' => ['az' => 'TBC Kredit', 'en' => 'TBC Credit', 'ru' => 'TBC Кредит'],
    'finex' => ['az' => 'FinEx', 'en' => 'FinEx', 'ru' => 'FinEx'],
    'pasha-capital' => ['az' => 'PASHA Capital', 'en' => 'PASHA Capital', 'ru' => 'ПАША Капитал'],
    'mcb-leasing' => ['az' => 'MCB Leasing', 'en' => 'MCB Leasing', 'ru' => 'MCB Лизинг'],
    'payme' => ['az' => 'PayMe', 'en' => 'PayMe', 'ru' => 'PayMe'],
];

// Get attribute type for name
$nameAttributeType = DB::table('attribute_types')
    ->where('type_name', 'text')
    ->first();

if (!$nameAttributeType) {
    echo "Text attribute type not found\n";
    exit(1);
}

// Get or create name attribute
$nameAttribute = DB::table('attributes')
    ->where('attribute_name', 'name')
    ->first();

if (!$nameAttribute) {
    $nameAttributeId = DB::table('attributes')->insertGetId([
        'attribute_name' => 'name',
        'attribute_type_id' => $nameAttributeType->id,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    echo "Created 'name' attribute with ID: $nameAttributeId\n";
} else {
    $nameAttributeId = $nameAttribute->id;
    echo "Using existing 'name' attribute with ID: $nameAttributeId\n";
}

// Add name to each company
$companies = DB::table('companies')->get();

foreach ($companies as $company) {
    $name = $companyNames[$company->slug] ?? [
        'az' => ucfirst(str_replace('-', ' ', $company->slug)),
        'en' => ucfirst(str_replace('-', ' ', $company->slug)),
        'ru' => ucfirst(str_replace('-', ' ', $company->slug))
    ];
    
    // Check if company already has name attribute
    $existing = DB::table('company_attributes')
        ->where('company_id', $company->id)
        ->where('attribute_id', $nameAttributeId)
        ->first();
    
    if ($existing) {
        // Update existing
        DB::table('company_attributes')
            ->where('id', $existing->id)
            ->update([
                'attribute_value' => json_encode($name),
                'updated_at' => now()
            ]);
        echo "Updated name for {$company->slug}\n";
    } else {
        // Insert new
        DB::table('company_attributes')->insert([
            'company_id' => $company->id,
            'attribute_id' => $nameAttributeId,
            'attribute_value' => json_encode($name),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        echo "Added name for {$company->slug}\n";
    }
}

echo "Done!\n";