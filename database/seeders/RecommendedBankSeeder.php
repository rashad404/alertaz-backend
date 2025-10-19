<?php

namespace Database\Seeders;

use App\Models\RecommendedBank;
use App\Models\Company;
use Illuminate\Database\Seeder;

class RecommendedBankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banks = [
            ['slug' => 'kapital-bank', 'order' => 1],
            ['slug' => 'pasa-bank', 'order' => 2],
            ['slug' => 'abb', 'order' => 3],
            ['slug' => 'tbc-kredit', 'order' => 4],
        ];

        foreach ($banks as $bank) {
            $company = Company::where('slug', $bank['slug'])->first();
            if ($company) {
                RecommendedBank::create([
                    'company_id' => $company->id,
                    'order' => $bank['order'],
                    'status' => true,
                ]);
            }
        }
    }
}