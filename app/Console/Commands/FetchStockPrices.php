<?php

namespace App\Console\Commands;

use App\Models\StockPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FetchStockPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:fetch-prices {--symbols= : Comma-separated list of symbols to fetch} {--force : Force fetch even outside market hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch latest stock prices from Twelve Data API';

    /**
     * Top 100 US stocks by market cap (grouped for batch processing)
     */
    private $top100Stocks = [
        // Batch 1 (8 symbols per request - Twelve Data free tier limit)
        ['AAPL', 'MSFT', 'GOOGL', 'AMZN', 'NVDA', 'META', 'TSLA', 'BRK.B'],
        ['V', 'UNH', 'XOM', 'JNJ', 'WMT', 'JPM', 'MA', 'PG'],
        ['AVGO', 'HD', 'CVX', 'MRK', 'ABBV', 'KO', 'PEP', 'COST'],
        ['ADBE', 'MCD', 'CSCO', 'TMO', 'ACN', 'LLY', 'NFLX', 'NKE'],
        ['ABT', 'ORCL', 'CRM', 'DHR', 'VZ', 'INTC', 'TXN', 'WFC'],
        ['PM', 'DIS', 'NEE', 'CMCSA', 'UPS', 'BMY', 'HON', 'UNP'],
        ['T', 'LOW', 'IBM', 'QCOM', 'BA', 'AMD', 'AMGN', 'SPGI'],
        ['ELV', 'INTU', 'RTX', 'BLK', 'CAT', 'GE', 'PLD', 'DE'],
        ['AXP', 'SBUX', 'GILD', 'NOW', 'MDLZ', 'ISRG', 'TJX', 'SYK'],
        ['ADP', 'BKNG', 'ADI', 'MMC', 'REGN', 'CI', 'ZTS', 'MO'],
        ['CVS', 'C', 'PGR', 'VRTX', 'DUK', 'SO', 'CB', 'BDX'],
        ['SCHW', 'ETN', 'BSX', 'AON', 'ITW', 'MMM', 'HUM', 'TGT'],
        ['LRCX', 'MU', 'PANW', 'EQIX'],
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if we're within market hours (9:30 AM - 4:00 PM ET)
        $now = Carbon::now('America/New_York');
        $marketOpen = Carbon::createFromTime(9, 30, 0, 'America/New_York');
        $marketClose = Carbon::createFromTime(16, 0, 0, 'America/New_York');
        $isWeekday = $now->isWeekday();
        $isMarketHours = $now->between($marketOpen, $marketClose);

        if (!$this->option('force') && (!$isWeekday || !$isMarketHours)) {
            $this->info('Market is closed. Skipping price fetch. Use --force to fetch anyway.');
            return 0;
        }

        $this->info('Fetching stock prices from Twelve Data API...');

        $apiKey = config('services.twelve_data.api_key');
        $baseUrl = config('services.twelve_data.base_url');

        if (empty($apiKey)) {
            $this->error('Twelve Data API key not configured. Set TWELVE_DATA_API_KEY in .env');
            Log::error('Twelve Data API key not configured');
            return 1;
        }

        // Check if specific symbols requested
        $symbolsOption = $this->option('symbols');
        $batches = $symbolsOption
            ? [explode(',', str_replace(' ', '', $symbolsOption))]
            : $this->top100Stocks;

        $totalUpdated = 0;
        $totalCreated = 0;
        $totalFailed = 0;

        foreach ($batches as $batchIndex => $batch) {
            try {
                $symbols = implode(',', $batch);
                $this->info("Fetching batch " . ($batchIndex + 1) . ": " . $symbols);

                $response = Http::timeout(30)->get("{$baseUrl}/quote", [
                    'symbol' => $symbols,
                    'apikey' => $apiKey,
                ]);

                if (!$response->successful()) {
                    $this->error("Failed to fetch batch {$batchIndex}: HTTP " . $response->status());
                    Log::error('Twelve Data API request failed', [
                        'batch' => $batchIndex,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    $totalFailed += count($batch);
                    continue;
                }

                $data = $response->json();

                // Handle single symbol response vs batch response
                $quotes = count($batch) === 1 ? [$data] : array_values($data);

                foreach ($quotes as $quote) {
                    if (!isset($quote['symbol']) || isset($quote['code'])) {
                        // Error in response
                        $this->warn("Skipping invalid quote: " . json_encode($quote));
                        $totalFailed++;
                        continue;
                    }

                    try {
                        $stockData = [
                            'symbol' => $quote['symbol'],
                            'name' => $quote['name'] ?? $quote['symbol'],
                            'exchange' => $quote['exchange'] ?? 'NASDAQ',
                            'current_price' => (float) $quote['close'],
                            'open' => isset($quote['open']) ? (float) $quote['open'] : null,
                            'high' => isset($quote['high']) ? (float) $quote['high'] : null,
                            'low' => isset($quote['low']) ? (float) $quote['low'] : null,
                            'previous_close' => isset($quote['previous_close']) ? (float) $quote['previous_close'] : null,
                            'change' => isset($quote['change']) ? (float) $quote['change'] : null,
                            'change_percent' => isset($quote['percent_change']) ? (float) $quote['percent_change'] : null,
                            'volume' => isset($quote['volume']) ? (int) $quote['volume'] : null,
                            'fifty_two_week_high' => isset($quote['fifty_two_week']['high']) ? (float) $quote['fifty_two_week']['high'] : null,
                            'fifty_two_week_low' => isset($quote['fifty_two_week']['low']) ? (float) $quote['fifty_two_week']['low'] : null,
                            'last_updated' => now(),
                        ];

                        $stockPrice = StockPrice::where('symbol', $quote['symbol'])->first();

                        if ($stockPrice) {
                            $stockPrice->update($stockData);
                            $totalUpdated++;
                        } else {
                            StockPrice::create($stockData);
                            $totalCreated++;
                        }

                        $this->line("  ✓ {$quote['symbol']}: \${$quote['close']}");
                    } catch (\Exception $e) {
                        $this->error("  ✗ Failed to process {$quote['symbol']}: " . $e->getMessage());
                        Log::error('Failed to process stock quote', [
                            'symbol' => $quote['symbol'],
                            'error' => $e->getMessage()
                        ]);
                        $totalFailed++;
                    }
                }

                // Rate limiting: Free tier allows 8 credits per minute (1 batch = 8 credits)
                // Sleep 60 seconds between batches to stay within limits
                if ($batchIndex < count($batches) - 1) {
                    $this->info('Waiting 60 seconds to respect API rate limits...');
                    sleep(60);
                }

            } catch (\Exception $e) {
                $this->error("Error fetching batch {$batchIndex}: " . $e->getMessage());
                Log::error('Error in FetchStockPrices command', [
                    'batch' => $batchIndex,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $totalFailed += count($batch);
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  Updated: {$totalUpdated}");
        $this->info("  Created: {$totalCreated}");
        if ($totalFailed > 0) {
            $this->warn("  Failed: {$totalFailed}");
        }

        Log::info("Stock prices updated", [
            'updated' => $totalUpdated,
            'created' => $totalCreated,
            'failed' => $totalFailed
        ]);

        return $totalFailed > 0 ? 1 : 0;
    }
}
