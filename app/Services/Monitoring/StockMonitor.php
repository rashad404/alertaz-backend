<?php

namespace App\Services\Monitoring;

use App\Models\PersonalAlert;
use App\Models\AlertType;
use App\Models\StockPrice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class StockMonitor extends BaseMonitor
{
    private const CACHE_TTL = 60; // Cache for 60 seconds (same as crypto)

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
     * Fetch current stock data for the alert from database.
     */
    protected function fetchCurrentData(PersonalAlert $alert): ?array
    {
        $symbol = $alert->asset; // Stock symbol is stored in asset field

        if (!$symbol) {
            Log::warning('Stock alert has no symbol', ['alert_id' => $alert->id]);
            return null;
        }

        $symbol = strtoupper($symbol);

        // Try to get cached data first
        $cacheKey = "stock_price_data_{$symbol}";
        $cachedData = Cache::get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        // Fetch from database
        $stockPrice = StockPrice::where('symbol', $symbol)->first();

        if (!$stockPrice) {
            Log::warning("Stock price not found in database", [
                'symbol' => $symbol,
                'alert_id' => $alert->id
            ]);
            return null;
        }

        // Check if data is stale (older than 5 minutes)
        if ($stockPrice->last_updated && $stockPrice->last_updated->diffInMinutes(now()) > 5) {
            Log::warning("Stock price data is stale", [
                'symbol' => $symbol,
                'last_updated' => $stockPrice->last_updated->toIso8601String(),
                'age_minutes' => $stockPrice->last_updated->diffInMinutes(now())
            ]);
        }

        // Format data for condition checking
        $data = [
            'symbol' => $stockPrice->symbol,
            'price' => (float) $stockPrice->current_price,
            'open' => $stockPrice->open ? (float) $stockPrice->open : null,
            'high' => $stockPrice->high ? (float) $stockPrice->high : null,
            'low' => $stockPrice->low ? (float) $stockPrice->low : null,
            'previous_close' => $stockPrice->previous_close ? (float) $stockPrice->previous_close : null,
            'change' => $stockPrice->change ? (float) $stockPrice->change : null,
            'change_percent' => $stockPrice->change_percent ? (float) $stockPrice->change_percent : null,
            'volume' => $stockPrice->volume ? (int) $stockPrice->volume : null,
            'market_cap' => $stockPrice->market_cap ? (float) $stockPrice->market_cap : null,
            'market_cap_rank' => $stockPrice->market_cap_rank,
            'fifty_two_week_high' => $stockPrice->fifty_two_week_high ? (float) $stockPrice->fifty_two_week_high : null,
            'fifty_two_week_low' => $stockPrice->fifty_two_week_low ? (float) $stockPrice->fifty_two_week_low : null,
            'exchange' => $stockPrice->exchange,
            'last_updated' => $stockPrice->last_updated->toIso8601String(),
        ];

        // Cache the formatted data
        Cache::put($cacheKey, $data, self::CACHE_TTL);

        return $data;
    }

    /**
     * Format alert message - returns simple identifier for frontend translation.
     */
    protected function formatAlertMessage(PersonalAlert $alert, array $currentData): string
    {
        // Return simple type identifier that frontend will translate
        return 'stock_target_reached';
    }
}
