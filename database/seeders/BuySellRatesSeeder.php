<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Currency;
use App\Models\Company;
use App\Models\BuySellRate;

class BuySellRatesSeeder extends Seeder
{
    public function run()
    {
        // Get all currencies
        $currencies = Currency::where('status', true)->get();
        
        // Get all banks (company_type_id = 1 is for banks)
        $banks = Company::where('company_type_id', 1)->where('status', true)->get();
        
        if ($banks->isEmpty()) {
            $this->command->warn('No banks found. Please run BankSeeder first.');
            return;
        }
        
        // Sample rate variations for different banks
        $rateVariations = [
            ['buy' => -0.005, 'sell' => 0.005],  // Bank 1: Small spread
            ['buy' => -0.008, 'sell' => 0.008],  // Bank 2: Medium spread
            ['buy' => -0.010, 'sell' => 0.010],  // Bank 3: Larger spread
            ['buy' => -0.006, 'sell' => 0.007],  // Bank 4: Asymmetric spread
            ['buy' => -0.004, 'sell' => 0.006],  // Bank 5: Small buy margin
        ];
        
        foreach ($currencies as $currency) {
            $centralRate = (float) $currency->central_bank_rate;
            
            foreach ($banks as $index => $bank) {
                $variation = $rateVariations[$index % count($rateVariations)];
                
                // Calculate buy and sell prices based on central rate
                $buyPrice = $centralRate * (1 + $variation['buy']);
                $sellPrice = $centralRate * (1 + $variation['sell']);
                
                // For small rates (like RUB, TRY), use smaller variations
                if ($centralRate < 0.1) {
                    $buyPrice = $centralRate - 0.0002;
                    $sellPrice = $centralRate + 0.0002;
                }
                
                BuySellRate::updateOrCreate(
                    [
                        'currency_id' => $currency->id,
                        'company_id' => $bank->id,
                    ],
                    [
                        'buy_price' => round($buyPrice, 4),
                        'sell_price' => round($sellPrice, 4),
                    ]
                );
                
                $this->command->info("Created rate for {$currency->currency} at {$bank->name}: Buy={$buyPrice}, Sell={$sellPrice}");
            }
        }
        
        $this->command->info('Buy/Sell rates seeded successfully!');
    }
}