<?php

namespace App\Console\Commands;

use App\Models\CryptoPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchCryptoPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto:fetch-prices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch latest cryptocurrency prices from CoinGecko API';

    /**
     * Popular coins in Azerbaijan
     */
    private $popularCoins = ['btc', 'eth', 'usdt', 'bnb', 'usdc', 'xrp'];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching cryptocurrency prices from CoinGecko...');
        
        $apiKey = config('services.coingecko.api_key');
        
        if (empty($apiKey)) {
            $this->error('CoinGecko API key not configured');
            return 1;
        }

        try {
            // Fetch top 100 coins by market cap
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'x-cg-demo-api-key' => $apiKey
            ])->get('https://api.coingecko.com/api/v3/coins/markets', [
                'vs_currency' => 'usd',
                'order' => 'market_cap_desc',
                'per_page' => 100,
                'page' => 1,
                'sparkline' => true,
                'price_change_percentage' => '1h,24h,7d,30d',
            ]);

            if ($response->successful()) {
                $coins = $response->json();
                $updated = 0;
                $created = 0;

                foreach ($coins as $coin) {
                    // Check if coin exists
                    $cryptoPrice = CryptoPrice::where('coin_id', $coin['id'])->first();
                    
                    $data = [
                        'coin_id' => $coin['id'],
                        'symbol' => $coin['symbol'],
                        'name' => $coin['name'],
                        'image' => $coin['image'] ?? null,
                        'current_price' => $coin['current_price'] ?? 0,
                        'market_cap' => $coin['market_cap'] ?? 0,
                        'market_cap_rank' => $coin['market_cap_rank'] ?? null,
                        'total_volume' => $coin['total_volume'] ?? 0,
                        'high_24h' => $coin['high_24h'] ?? null,
                        'low_24h' => $coin['low_24h'] ?? null,
                        'price_change_24h' => $coin['price_change_24h'] ?? null,
                        'price_change_percentage_24h' => $coin['price_change_percentage_24h'] ?? null,
                        'price_change_percentage_1h' => $coin['price_change_percentage_1h_in_currency'] ?? null,
                        'price_change_percentage_7d' => $coin['price_change_percentage_7d_in_currency'] ?? null,
                        'price_change_percentage_30d' => $coin['price_change_percentage_30d_in_currency'] ?? null,
                        'circulating_supply' => $coin['circulating_supply'] ?? null,
                        'total_supply' => $coin['total_supply'] ?? null,
                        'max_supply' => $coin['max_supply'] ?? null,
                        'sparkline_7d' => isset($coin['sparkline_in_7d']) ? $coin['sparkline_in_7d']['price'] : null,
                        'popular_in_azerbaijan' => in_array($coin['symbol'], $this->popularCoins),
                        'last_updated' => now(),
                    ];

                    if ($cryptoPrice) {
                        $cryptoPrice->update($data);
                        $updated++;
                    } else {
                        CryptoPrice::create($data);
                        $created++;
                    }
                }

                $this->info("Successfully updated {$updated} and created {$created} cryptocurrency prices");
                Log::info("Crypto prices updated: {$updated} updated, {$created} created");
                
                return 0;
            } else {
                $this->error('Failed to fetch data from CoinGecko API');
                Log::error('CoinGecko API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('Error fetching crypto prices: ' . $e->getMessage());
            Log::error('Error in FetchCryptoPrices command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}