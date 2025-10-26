<?php

namespace App\Services\Monitoring;

use App\Models\PersonalAlert;
use App\Models\AlertType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CurrencyMonitor extends BaseMonitor
{
    private const CBAR_API_URL = 'https://www.cbar.az/currencies';
    private const EXCHANGERATE_API_URL = 'https://api.exchangerate-api.com/v4/latest';
    private const CACHE_TTL = 1800; // Cache for 30 minutes

    /**
     * Check all active currency alerts.
     */
    public function checkAlerts(): void
    {
        $alertType = AlertType::where('slug', 'currency')->first();

        if (!$alertType) {
            Log::warning('Currency alert type not found');
            return;
        }

        $alerts = PersonalAlert::active()
            ->where('alert_type_id', $alertType->id)
            ->needsChecking()
            ->get();

        Log::info("Checking {$alerts->count()} currency alerts");

        foreach ($alerts as $alert) {
            $this->processAlert($alert);
        }
    }

    /**
     * Fetch current currency data for the alert.
     */
    protected function fetchCurrentData(PersonalAlert $alert): ?array
    {
        $currencyPair = $alert->asset; // e.g., "USD/AZN", "EUR/AZN"

        if (!$currencyPair) {
            return null;
        }

        // Parse currency pair
        $currencies = explode('/', $currencyPair);
        if (count($currencies) !== 2) {
            Log::error("Invalid currency pair format: {$currencyPair}");
            return null;
        }

        [$fromCurrency, $toCurrency] = $currencies;

        // Try to get cached data first
        $cacheKey = "currency_rate_" . str_replace('/', '_', $currencyPair);
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        // For AZN pairs, try CBAR first
        if ($toCurrency === 'AZN' || $fromCurrency === 'AZN') {
            $data = $this->fetchFromCBAR($fromCurrency, $toCurrency);
        } else {
            $data = null;
        }

        // If CBAR fails or not AZN pair, try ExchangeRate API
        if (!$data) {
            $data = $this->fetchFromExchangeRateAPI($fromCurrency, $toCurrency);
        }

        // If both fail, use mock data
        if (!$data) {
            $data = $this->getMockCurrencyData($fromCurrency, $toCurrency);
        }

        if ($data) {
            // Cache the data
            Cache::put($cacheKey, $data, self::CACHE_TTL);
        }

        return $data;
    }

    /**
     * Fetch currency data from CBAR (Central Bank of Azerbaijan).
     */
    private function fetchFromCBAR(string $fromCurrency, string $toCurrency): ?array
    {
        try {
            // CBAR provides rates for today's date
            $date = now()->format('d.m.Y');
            $response = Http::timeout(10)->get(self::CBAR_API_URL . '/' . $date . '.xml');

            if ($response->successful()) {
                $xml = simplexml_load_string($response->body());

                if (!$xml) {
                    return null;
                }

                $rates = [];
                foreach ($xml->ValType[1]->Valute as $valute) {
                    $code = (string) $valute['Code'];
                    $nominal = (float) $valute->Nominal;
                    $value = (float) $valute->Value;
                    $rates[$code] = $value / $nominal; // Rate per 1 unit
                }

                // Add AZN as base (1 AZN = 1 AZN)
                $rates['AZN'] = 1.0;

                // Calculate the exchange rate
                if ($toCurrency === 'AZN' && isset($rates[$fromCurrency])) {
                    $rate = $rates[$fromCurrency];
                } elseif ($fromCurrency === 'AZN' && isset($rates[$toCurrency])) {
                    $rate = 1 / $rates[$toCurrency];
                } elseif (isset($rates[$fromCurrency]) && isset($rates[$toCurrency])) {
                    // Cross rate
                    $rate = $rates[$fromCurrency] / $rates[$toCurrency];
                } else {
                    return null;
                }

                // Get yesterday's rate for change calculation
                $yesterdayRate = $this->getYesterdayRate($fromCurrency, $toCurrency);
                $change = $yesterdayRate ? ($rate - $yesterdayRate) : 0;
                $changePercent = $yesterdayRate ? (($change / $yesterdayRate) * 100) : 0;

                return [
                    'from_currency' => $fromCurrency,
                    'to_currency' => $toCurrency,
                    'rate' => round($rate, 4),
                    'bid' => round($rate * 0.995, 4), // Approximate bid (0.5% spread)
                    'ask' => round($rate * 1.005, 4), // Approximate ask (0.5% spread)
                    'change' => round($change, 4),
                    'change_percent' => round($changePercent, 2),
                    'previous_rate' => $yesterdayRate,
                    'source' => 'cbar',
                    'timestamp' => now()->toIso8601String(),
                ];
            }
        } catch (\Exception $e) {
            Log::warning("CBAR API error for {$fromCurrency}/{$toCurrency}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Fetch currency data from ExchangeRate API.
     */
    private function fetchFromExchangeRateAPI(string $fromCurrency, string $toCurrency): ?array
    {
        try {
            $response = Http::timeout(10)->get(self::EXCHANGERATE_API_URL . '/' . $fromCurrency);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['rates'][$toCurrency])) {
                    $rate = $data['rates'][$toCurrency];

                    // Get yesterday's rate for change calculation
                    $yesterdayRate = $this->getYesterdayRate($fromCurrency, $toCurrency);
                    $change = $yesterdayRate ? ($rate - $yesterdayRate) : 0;
                    $changePercent = $yesterdayRate ? (($change / $yesterdayRate) * 100) : 0;

                    return [
                        'from_currency' => $fromCurrency,
                        'to_currency' => $toCurrency,
                        'rate' => round($rate, 4),
                        'bid' => round($rate * 0.998, 4), // Approximate bid
                        'ask' => round($rate * 1.002, 4), // Approximate ask
                        'change' => round($change, 4),
                        'change_percent' => round($changePercent, 2),
                        'previous_rate' => $yesterdayRate,
                        'source' => 'exchangerate-api',
                        'timestamp' => now()->toIso8601String(),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning("ExchangeRate API error for {$fromCurrency}/{$toCurrency}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Get yesterday's rate from cache or calculate.
     */
    private function getYesterdayRate(string $fromCurrency, string $toCurrency): ?float
    {
        // Try to get from cache
        $yesterdayCacheKey = "currency_yesterday_" . $fromCurrency . '_' . $toCurrency;
        $cachedRate = Cache::get($yesterdayCacheKey);

        if ($cachedRate !== null) {
            return $cachedRate;
        }

        // Generate a mock previous rate based on current rate
        // In production, this would fetch historical data
        return null;
    }

    /**
     * Get mock currency data for development.
     */
    private function getMockCurrencyData(string $fromCurrency, string $toCurrency): array
    {
        // Base rates (to USD)
        $baseRates = [
            'USD' => 1.0,
            'EUR' => 0.92,
            'GBP' => 0.79,
            'AZN' => 1.70,
            'RUB' => 90.5,
            'TRY' => 32.8,
            'JPY' => 148.5,
            'CHF' => 0.88,
            'CAD' => 1.36,
            'AUD' => 1.52,
        ];

        // Calculate rate
        $fromRate = $baseRates[$fromCurrency] ?? 1.0;
        $toRate = $baseRates[$toCurrency] ?? 1.0;
        $rate = $toRate / $fromRate;

        // Add some random variation
        $variation = (rand(-100, 100) / 10000); // ±1%
        $rate = $rate * (1 + $variation);

        // Calculate change
        $change = $rate * (rand(-200, 200) / 10000); // ±2%
        $changePercent = ($change / $rate) * 100;

        return [
            'from_currency' => $fromCurrency,
            'to_currency' => $toCurrency,
            'rate' => round($rate, 4),
            'bid' => round($rate * 0.998, 4),
            'ask' => round($rate * 1.002, 4),
            'change' => round($change, 4),
            'change_percent' => round($changePercent, 2),
            'previous_rate' => round($rate - $change, 4),
            'source' => 'mock',
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Format alert message - returns simple identifier for frontend translation.
     */
    protected function formatAlertMessage(PersonalAlert $alert, array $currentData): string
    {
        // Return simple type identifier that frontend will translate
        return 'currency_target_reached';
    }
}