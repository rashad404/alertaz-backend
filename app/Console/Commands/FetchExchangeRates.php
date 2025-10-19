<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExchangeRate;
use App\Models\Currency;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange-rates:fetch {--date= : Date in DD.MM.YYYY format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch exchange rates from CBAR (Central Bank of Azerbaijan)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->option('date') ?: now()->format('d.m.Y');
        $this->info("Fetching exchange rates for date: {$date}");

        try {
            // Fetch XML from CBAR
            $url = "https://cbar.az/currencies/{$date}.xml";
            $response = Http::get($url);

            if (!$response->successful()) {
                $this->error("Failed to fetch data from CBAR");
                return Command::FAILURE;
            }

            // Parse XML
            $xml = simplexml_load_string($response->body());
            if (!$xml) {
                $this->error("Failed to parse XML response");
                return Command::FAILURE;
            }

            $rateDate = Carbon::createFromFormat('d.m.Y', (string)$xml['Date']);
            $this->info("Processing rates for: " . $rateDate->toDateString());

            // Process foreign currencies
            $foreignCurrencies = $xml->xpath('//ValType[@Type="Xarici valyutalar"]/Valute');
            
            $count = 0;
            foreach ($foreignCurrencies as $currency) {
                $code = (string)$currency['Code'];
                $nominal = (float)str_replace(',', '.', (string)$currency->Nominal);
                $name = (string)$currency->Name;
                $value = (float)str_replace(',', '.', (string)$currency->Value);

                // Update or create exchange rate
                ExchangeRate::updateOrCreate(
                    [
                        'currency_code' => $code,
                        'rate_date' => $rateDate->toDateString(),
                        'source' => 'CBAR'
                    ],
                    [
                        'currency_name' => $name,
                        'rate' => $value,
                        'nominal' => $nominal,
                    ]
                );

                // Update Currency table if exists
                Currency::where('currency', $code)->update([
                    'central_bank_rate' => $value / $nominal
                ]);

                $count++;
                $this->info("Updated: {$code} - {$value}/{$nominal} = " . ($value/$nominal));
            }

            // Process bank metals (gold, silver, etc.)
            $metals = $xml->xpath('//ValType[@Type="Bank metalları"]/Valute');
            
            foreach ($metals as $metal) {
                $code = (string)$metal['Code'];
                $nominal = 1; // Metals are always per 1 troy ounce
                $name = (string)$metal->Name;
                $value = (float)str_replace(',', '.', (string)$metal->Value);

                ExchangeRate::updateOrCreate(
                    [
                        'currency_code' => $code,
                        'rate_date' => $rateDate->toDateString(),
                        'source' => 'CBAR'
                    ],
                    [
                        'currency_name' => $name,
                        'rate' => $value,
                        'nominal' => $nominal,
                    ]
                );

                $count++;
                $this->info("Updated metal: {$code} ({$name}) - {$value}");
            }

            $this->info("Successfully updated {$count} exchange rates");

            // Log the update
            Log::info("Exchange rates updated", [
                'date' => $rateDate->toDateString(),
                'count' => $count
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error fetching exchange rates: " . $e->getMessage());
            Log::error("Exchange rates fetch failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
