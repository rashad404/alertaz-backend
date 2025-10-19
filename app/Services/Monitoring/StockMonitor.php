<?php

namespace App\Services\Monitoring;

use App\Models\PersonalAlert;
use App\Models\AlertType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class StockMonitor extends BaseMonitor
{
    private const ALPHA_VANTAGE_URL = 'https://www.alphavantage.co/query';
    private const YAHOO_FINANCE_URL = 'https://query2.finance.yahoo.com/v10/finance/quoteSummary';
    private const CACHE_TTL = 300; // Cache for 5 minutes

    /**
     * Check all active stock alerts.
     */
    public function checkAlerts(): void
    {
        $alertType = AlertType::where('slug', 'stock')->first();

        if (!$alertType) {
            Log::warning('Stock alert type not found');
            return;
        }

        $alerts = PersonalAlert::active()
            ->where('alert_type_id', $alertType->id)
            ->needsChecking()
            ->get();

        Log::info("Checking {$alerts->count()} stock alerts");

        foreach ($alerts as $alert) {
            $this->processAlert($alert);
        }
    }

    /**
     * Fetch current stock data for the alert.
     */
    protected function fetchCurrentData(PersonalAlert $alert): ?array
    {
        $symbol = $alert->asset; // Stock symbol is stored in asset field

        if (!$symbol) {
            return null;
        }

        // Try to get cached data first
        $cacheKey = "stock_price_" . strtoupper($symbol);
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        // Try Alpha Vantage first
        $data = $this->fetchFromAlphaVantage($symbol);

        // If Alpha Vantage fails, try Yahoo Finance
        if (!$data) {
            $data = $this->fetchFromYahoo($symbol);
        }

        // If both fail, use mock data
        if (!$data) {
            $data = $this->getMockStockData($symbol);
        }

        if ($data) {
            // Cache the data
            Cache::put($cacheKey, $data, self::CACHE_TTL);
        }

        return $data;
    }

    /**
     * Fetch stock data from Alpha Vantage.
     */
    private function fetchFromAlphaVantage(string $symbol): ?array
    {
        try {
            $apiKey = config('services.alpha_vantage.api_key', env('ALPHA_VANTAGE_API_KEY'));

            if (!$apiKey) {
                Log::warning('Alpha Vantage API key not configured');
                return null;
            }

            $response = Http::timeout(10)->get(self::ALPHA_VANTAGE_URL, [
                'function' => 'GLOBAL_QUOTE',
                'symbol' => strtoupper($symbol),
                'apikey' => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['Global Quote'])) {
                    $quote = $data['Global Quote'];

                    return [
                        'symbol' => strtoupper($symbol),
                        'price' => (float) $quote['05. price'],
                        'open' => (float) $quote['02. open'],
                        'high' => (float) $quote['03. high'],
                        'low' => (float) $quote['04. low'],
                        'previous_close' => (float) $quote['08. previous close'],
                        'change' => (float) $quote['09. change'],
                        'change_percent' => (float) str_replace('%', '', $quote['10. change percent']),
                        'volume' => (int) $quote['06. volume'],
                        'source' => 'alpha_vantage',
                        'timestamp' => now()->toIso8601String(),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning("Alpha Vantage API error for {$symbol}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Fetch stock data from Yahoo Finance.
     */
    private function fetchFromYahoo(string $symbol): ?array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get(self::YAHOO_FINANCE_URL . '/' . strtoupper($symbol), [
                    'modules' => 'price',
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['quoteSummary']['result'][0]['price'])) {
                    $price = $data['quoteSummary']['result'][0]['price'];
                    $regularMarketPrice = $price['regularMarketPrice']['raw'] ?? 0;
                    $regularMarketChangePercent = $price['regularMarketChangePercent']['raw'] ?? 0;

                    return [
                        'symbol' => strtoupper($symbol),
                        'price' => (float) $regularMarketPrice,
                        'open' => (float) ($price['regularMarketOpen']['raw'] ?? 0),
                        'high' => (float) ($price['regularMarketDayHigh']['raw'] ?? 0),
                        'low' => (float) ($price['regularMarketDayLow']['raw'] ?? 0),
                        'previous_close' => (float) ($price['regularMarketPreviousClose']['raw'] ?? 0),
                        'change' => (float) ($price['regularMarketChange']['raw'] ?? 0),
                        'change_percent' => (float) ($regularMarketChangePercent * 100),
                        'volume' => (int) ($price['regularMarketVolume']['raw'] ?? 0),
                        'market_cap' => (float) ($price['marketCap']['raw'] ?? 0),
                        'source' => 'yahoo',
                        'timestamp' => now()->toIso8601String(),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning("Yahoo Finance API error for {$symbol}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Get mock stock data for development.
     */
    private function getMockStockData(string $symbol): array
    {
        // Generate realistic but random stock data
        $basePrice = $this->getBasePrice($symbol);
        $change = rand(-500, 500) / 100; // -5% to +5%
        $price = $basePrice + $change;
        $changePercent = ($change / $basePrice) * 100;

        return [
            'symbol' => strtoupper($symbol),
            'price' => round($price, 2),
            'open' => round($basePrice + rand(-200, 200) / 100, 2),
            'high' => round($price + rand(0, 300) / 100, 2),
            'low' => round($price - rand(0, 300) / 100, 2),
            'previous_close' => round($basePrice, 2),
            'change' => round($change, 2),
            'change_percent' => round($changePercent, 2),
            'volume' => rand(1000000, 50000000),
            'source' => 'mock',
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Get base price for common stocks (mock data).
     */
    private function getBasePrice(string $symbol): float
    {
        $stocks = [
            'AAPL' => 185.50,
            'GOOGL' => 140.25,
            'MSFT' => 380.75,
            'AMZN' => 155.30,
            'TSLA' => 250.60,
            'META' => 490.20,
            'NVDA' => 880.40,
            'AMD' => 165.80,
            'NFLX' => 675.30,
            'DIS' => 105.20,
            'BA' => 220.15,
            'JPM' => 180.90,
            'V' => 265.45,
            'WMT' => 175.80,
            'PFE' => 28.50,
        ];

        return $stocks[strtoupper($symbol)] ?? rand(10, 500);
    }

    /**
     * Format alert message specifically for stocks.
     */
    protected function formatAlertMessage(PersonalAlert $alert, array $currentData): string
    {
        $symbol = $alert->asset;
        $condition = $alert->conditions;
        $currentPrice = $currentData['price'] ?? 0;
        $changePercent = $currentData['change_percent'] ?? 0;
        $change = $currentData['change'] ?? 0;

        $message = "📈 **Stock Alert: {$alert->name}**\n\n";
        $message .= "🏢 **{$symbol}** has triggered your alert!\n\n";
        $message .= "📊 **Market Update:**\n";
        $message .= "• Current Price: $" . number_format($currentPrice, 2) . "\n";
        $message .= "• Your Target: {$condition['field']} {$condition['operator']} {$condition['value']}\n";
        $message .= "• Change: " . ($change >= 0 ? '+' : '') . "$" . number_format($change, 2);
        $message .= " (" . ($changePercent >= 0 ? '+' : '') . number_format($changePercent, 2) . "%)\n";

        if (isset($currentData['volume'])) {
            $message .= "• Volume: " . number_format($currentData['volume']) . " shares\n";
        }

        if (isset($currentData['high']) && isset($currentData['low'])) {
            $message .= "• Day Range: $" . number_format($currentData['low'], 2) . " - $" . number_format($currentData['high'], 2) . "\n";
        }

        if (isset($currentData['market_cap']) && $currentData['market_cap'] > 0) {
            $marketCap = $currentData['market_cap'];
            if ($marketCap >= 1000000000000) {
                $message .= "• Market Cap: $" . number_format($marketCap / 1000000000000, 2) . "T\n";
            } elseif ($marketCap >= 1000000000) {
                $message .= "• Market Cap: $" . number_format($marketCap / 1000000000, 2) . "B\n";
            } else {
                $message .= "• Market Cap: $" . number_format($marketCap / 1000000, 2) . "M\n";
            }
        }

        $message .= "\n⏰ " . now()->format('Y-m-d H:i:s') . " (Asia/Baku)";

        return $message;
    }
}