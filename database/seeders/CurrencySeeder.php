<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            [
                'currency' => 'USD',
                'central_bank_rate' => 1.7000,
                'order' => 1,
                'status' => true,
            ],
            [
                'currency' => 'EUR',
                'central_bank_rate' => 1.8500,
                'order' => 2,
                'status' => true,
            ],
            [
                'currency' => 'GBP',
                'central_bank_rate' => 2.1500,
                'order' => 3,
                'status' => true,
            ],
            [
                'currency' => 'RUB',
                'central_bank_rate' => 0.0185,
                'order' => 4,
                'status' => true,
            ],
            [
                'currency' => 'TRY',
                'central_bank_rate' => 0.0520,
                'order' => 5,
                'status' => true,
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::create($currency);
        }
    }
}