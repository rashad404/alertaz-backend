<?php

namespace App\Services\Monitoring;

use App\Models\PersonalAlert;
use App\Models\AlertType;
use App\Models\CryptoPrice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CryptoMonitor extends BaseMonitor
{
    private const CACHE_TTL = 60; // Cache for 1 minute to avoid excessive DB queries

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
     * Fetch current crypto data for the alert from database.
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

        // Fetch from database
        $cryptoPrice = CryptoPrice::where('symbol', strtolower($asset))
            ->orWhere('coin_id', strtolower($asset))
            ->first();

        if (!$cryptoPrice) {
            Log::warning("Crypto price not found in database for: {$asset}");
            return null;
        }

        // Format data for alert checking
        $data = [
            'symbol' => strtoupper($cryptoPrice->symbol),
            'price' => (float) $cryptoPrice->current_price,
            'change_24h' => (float) $cryptoPrice->price_change_percentage_24h,
            'change_1h' => (float) $cryptoPrice->price_change_percentage_1h,
            'change_7d' => (float) $cryptoPrice->price_change_percentage_7d,
            'volume' => (float) $cryptoPrice->total_volume,
            'high_24h' => (float) $cryptoPrice->high_24h,
            'low_24h' => (float) $cryptoPrice->low_24h,
            'market_cap' => (float) $cryptoPrice->market_cap,
            'market_cap_rank' => $cryptoPrice->market_cap_rank,
            'source' => 'database',
            'timestamp' => $cryptoPrice->last_updated?->toIso8601String() ?? now()->toIso8601String(),
        ];

        // Cache the data
        Cache::put($cacheKey, $data, self::CACHE_TTL);

        return $data;
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