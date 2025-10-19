<?php

namespace App\Services\Monitoring;

use App\Models\PersonalAlert;
use App\Models\AlertType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CryptoMonitor extends BaseMonitor
{
    private const BINANCE_API_URL = 'https://api.binance.com/api/v3';
    private const COINGECKO_API_URL = 'https://api.coingecko.com/api/v3';
    private const CACHE_TTL = 60; // Cache for 1 minute to avoid rate limits

    /**
     * Check all active crypto alerts.
     */
    public function checkAlerts(): void
    {
        $alertType = AlertType::where('slug', 'crypto')->first();

        if (!$alertType) {
            Log::warning('Crypto alert type not found');
            return;
        }

        $alerts = PersonalAlert::active()
            ->where('alert_type_id', $alertType->id)
            ->needsChecking()
            ->get();

        Log::info("Checking {$alerts->count()} crypto alerts");

        foreach ($alerts as $alert) {
            $this->processAlert($alert);
        }
    }

    /**
     * Fetch current crypto data for the alert.
     */
    protected function fetchCurrentData(PersonalAlert $alert): ?array
    {
        $asset = $alert->asset;

        if (!$asset) {
            return null;
        }

        // Try to get cached data first
        $cacheKey = "crypto_price_{$asset}";
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        // Try Binance first
        $data = $this->fetchFromBinance($asset);

        // If Binance fails, try CoinGecko
        if (!$data) {
            $data = $this->fetchFromCoinGecko($asset);
        }

        if ($data) {
            // Cache the data
            Cache::put($cacheKey, $data, self::CACHE_TTL);
        }

        return $data;
    }

    /**
     * Fetch price from Binance.
     */
    private function fetchFromBinance(string $symbol): ?array
    {
        try {
            // Convert symbol to Binance format (e.g., BTC -> BTCUSDT)
            $pair = $symbol . 'USDT';

            $response = Http::timeout(10)->get(self::BINANCE_API_URL . '/ticker/24hr', [
                'symbol' => $pair,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'symbol' => $symbol,
                    'price' => (float) $data['lastPrice'],
                    'change_24h' => (float) $data['priceChangePercent'],
                    'volume' => (float) $data['volume'],
                    'high_24h' => (float) $data['highPrice'],
                    'low_24h' => (float) $data['lowPrice'],
                    'source' => 'binance',
                    'timestamp' => now()->toIso8601String(),
                ];
            }
        } catch (\Exception $e) {
            Log::warning("Binance API error for {$symbol}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Fetch price from CoinGecko.
     */
    private function fetchFromCoinGecko(string $symbol): ?array
    {
        try {
            // Map common symbols to CoinGecko IDs
            $coinIds = [
                'BTC' => 'bitcoin',
                'ETH' => 'ethereum',
                'BNB' => 'binancecoin',
                'ADA' => 'cardano',
                'SOL' => 'solana',
                'XRP' => 'ripple',
                'DOT' => 'polkadot',
                'DOGE' => 'dogecoin',
                'AVAX' => 'avalanche-2',
                'MATIC' => 'matic-network',
                'SHIB' => 'shiba-inu',
                'LTC' => 'litecoin',
                'UNI' => 'uniswap',
                'LINK' => 'chainlink',
                'ATOM' => 'cosmos',
            ];

            $coinId = $coinIds[strtoupper($symbol)] ?? strtolower($symbol);

            $response = Http::timeout(10)->get(self::COINGECKO_API_URL . '/simple/price', [
                'ids' => $coinId,
                'vs_currencies' => 'usd',
                'include_24hr_change' => 'true',
                'include_24hr_vol' => 'true',
                'include_last_updated_at' => 'true',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data[$coinId])) {
                    $coinData = $data[$coinId];

                    return [
                        'symbol' => $symbol,
                        'price' => (float) $coinData['usd'],
                        'change_24h' => (float) ($coinData['usd_24h_change'] ?? 0),
                        'volume' => (float) ($coinData['usd_24h_vol'] ?? 0),
                        'source' => 'coingecko',
                        'timestamp' => now()->toIso8601String(),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning("CoinGecko API error for {$symbol}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Format alert message specifically for crypto.
     */
    protected function formatAlertMessage(PersonalAlert $alert, array $currentData): string
    {
        $symbol = $alert->asset;
        $condition = $alert->conditions;
        $currentPrice = $currentData['price'] ?? 0;
        $change24h = $currentData['change_24h'] ?? 0;

        $message = "🚨 **Crypto Alert: {$alert->name}**\n\n";
        $message .= "💰 **{$symbol}** has reached your target!\n\n";
        $message .= "📊 **Details:**\n";
        $message .= "• Current Price: $" . number_format($currentPrice, 2) . "\n";
        $message .= "• Your Target: {$condition['field']} {$condition['operator']} {$condition['value']}\n";
        $message .= "• 24h Change: " . ($change24h >= 0 ? '+' : '') . number_format($change24h, 2) . "%\n";

        if (isset($currentData['volume'])) {
            $message .= "• 24h Volume: $" . number_format($currentData['volume'], 0) . "\n";
        }

        $message .= "\n⏰ " . now()->format('Y-m-d H:i:s') . " (Asia/Baku)";

        return $message;
    }
}