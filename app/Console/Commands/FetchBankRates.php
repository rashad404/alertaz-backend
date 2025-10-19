<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\Currency;
use App\Models\BuySellRate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\BankRateScraperService;

class FetchBankRates extends Command
{
    protected $signature = 'bank-rates:fetch {--bank= : Specific bank to fetch rates for}';
    protected $description = 'Fetch exchange rates from individual banks';
    
    protected $scraperService;

    public function __construct(BankRateScraperService $scraperService)
    {
        parent::__construct();
        $this->scraperService = $scraperService;
    }

    // Bank configurations for scraping
    protected $bankConfigs = [
        'kapital-bank' => [
            'name' => 'Kapital Bank',
            'slug' => 'kapital-bank2',  // Fixed: actual slug in database
            'method' => 'fetchKapitalBank'
        ],
        'pasha-bank' => [
            'name' => 'PASHA Bank',
            'slug' => 'pasha-bank',
            'method' => 'fetchPashaBank'
        ],
        'abb' => [
            'name' => 'ABB',
            'slug' => 'abb',
            'method' => 'fetchABB'
        ],
        'bank-respublika' => [
            'name' => 'Bank Respublika',
            'slug' => 'bank-respublika',
            'method' => 'fetchBankRespublika'
        ],
        'unibank' => [
            'name' => 'Unibank',
            'slug' => 'unibank',
            'method' => 'fetchUnibank'
        ]
    ];

    public function handle()
    {
        $specificBank = $this->option('bank');
        
        if ($specificBank) {
            if (!isset($this->bankConfigs[$specificBank])) {
                $this->error("Unknown bank: $specificBank");
                return 1;
            }
            $this->fetchBankRates($specificBank, $this->bankConfigs[$specificBank]);
        } else {
            foreach ($this->bankConfigs as $bankSlug => $config) {
                $this->fetchBankRates($bankSlug, $config);
            }
        }
        
        $this->info('Bank rates fetched successfully!');
        return 0;
    }

    protected function fetchBankRates($bankSlug, $config)
    {
        $this->info("Fetching rates for {$config['name']}...");
        
        try {
            $method = $config['method'];
            $rates = $this->scraperService->$method();
            
            if ($rates) {
                $this->saveRates($config['slug'], $rates);
                $this->info("✓ Rates saved for {$config['name']}");
            } else {
                $this->warn("No rates found for {$config['name']}");
            }
        } catch (\Exception $e) {
            $this->error("Failed to fetch rates for {$config['name']}: " . $e->getMessage());
            Log::error("Bank rate fetch failed for {$config['name']}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function saveRates($bankSlug, $rates)
    {
        // Find the bank company by slug (more reliable)
        $company = Company::where('slug', $bankSlug)->first();

        if (!$company) {
            $this->warn("Company not found with slug: {$bankSlug}");
            return;
        }

        foreach ($rates as $currencyCode => $rate) {
            $currency = Currency::where('currency', $currencyCode)->first();
            
            if (!$currency) {
                $this->warn("Currency not found: {$currencyCode}");
                continue;
            }

            BuySellRate::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'currency_id' => $currency->id
                ],
                [
                    'buy_price' => $rate['buy'],
                    'sell_price' => $rate['sell']
                ]
            );
        }
    }
}