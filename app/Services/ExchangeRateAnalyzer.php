<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ExchangeRateAnalyzer
{
    /**
     * Main currencies to track for news generation
     */
    protected const MAIN_CURRENCIES = ['USD', 'EUR', 'RUB', 'GBP'];

    /**
     * Get latest exchange rates for main currencies
     *
     * @return array Associative array [currency_code => actual_rate]
     */
    public function getLatestRates(): array
    {
        $latestDate = ExchangeRate::max('rate_date');

        if (!$latestDate) {
            Log::warning('No exchange rates found in database');
            return [];
        }

        $rates = ExchangeRate::where('rate_date', $latestDate)
            ->whereIn('currency_code', self::MAIN_CURRENCIES)
            ->get();

        return $this->formatRates($rates);
    }

    /**
     * Get exchange rates from a specific date or X hours ago
     *
     * @param int $hoursAgo Number of hours to go back
     * @return array
     */
    public function getRatesFromHoursAgo(int $hoursAgo): array
    {
        $targetDate = now()->subHours($hoursAgo);

        $rate = ExchangeRate::where('rate_date', '<=', $targetDate->toDateString())
            ->whereIn('currency_code', self::MAIN_CURRENCIES)
            ->orderBy('rate_date', 'desc')
            ->first();

        if (!$rate) {
            return $this->getLatestRates();
        }

        $rates = ExchangeRate::where('rate_date', $rate->rate_date)
            ->whereIn('currency_code', self::MAIN_CURRENCIES)
            ->get();

        return $this->formatRates($rates);
    }

    /**
     * Get yesterday's exchange rates
     *
     * @return array
     */
    public function getYesterdayRates(): array
    {
        $yesterday = now()->subDay()->toDateString();

        $rates = ExchangeRate::where('rate_date', '<=', $yesterday)
            ->whereIn('currency_code', self::MAIN_CURRENCIES)
            ->orderBy('rate_date', 'desc')
            ->get()
            ->unique('currency_code')
            ->take(count(self::MAIN_CURRENCIES));

        return $this->formatRates($rates);
    }

    /**
     * Calculate percentage changes between two rate sets
     *
     * @param array $currentRates
     * @param array $previousRates
     * @return array [currency_code => ['change_percent' => float, 'current' => float, 'previous' => float]]
     */
    public function calculateChanges(array $currentRates, array $previousRates): array
    {
        $changes = [];

        foreach ($currentRates as $code => $currentRate) {
            if (!isset($previousRates[$code])) {
                continue;
            }

            $previousRate = $previousRates[$code];
            $change = (($currentRate - $previousRate) / $previousRate) * 100;

            $changes[$code] = [
                'current_rate' => $currentRate,
                'previous_rate' => $previousRate,
                'change_percent' => round($change, 2),
                'change_amount' => round($currentRate - $previousRate, 4),
            ];
        }

        return $changes;
    }

    /**
     * Detect breaking news based on threshold
     *
     * @param float $threshold Percentage threshold (e.g., 0.5 for 0.5%)
     * @param int $hoursAgo How many hours to compare with
     * @return array Array of breaking news items [['currency' => 'USD', 'data' => [...]]]
     */
    public function detectBreakingNews(float $threshold = null, int $hoursAgo = 2): array
    {
        $threshold = $threshold ?? config('ai.thresholds.exchange_rates', 0.5);

        $currentRates = $this->getLatestRates();
        $previousRates = $this->getRatesFromHoursAgo($hoursAgo);

        if (empty($currentRates) || empty($previousRates)) {
            Log::info('No rates available for breaking news detection');
            return [];
        }

        $changes = $this->calculateChanges($currentRates, $previousRates);
        $breakingNews = [];

        foreach ($changes as $currency => $data) {
            if (abs($data['change_percent']) >= $threshold) {
                $breakingNews[] = [
                    'currency' => $currency,
                    'data' => $data,
                ];

                Log::info('Breaking news detected', [
                    'currency' => $currency,
                    'change_percent' => $data['change_percent'],
                    'threshold' => $threshold
                ]);
            }
        }

        return $breakingNews;
    }

    /**
     * Get prepared data for daily summary generation
     *
     * @return array ['current' => [], 'previous' => []]
     */
    public function getDataForDailySummary(): array
    {
        $currentRates = $this->getLatestRates();
        $yesterdayRates = $this->getYesterdayRates();

        return [
            'current' => $currentRates,
            'previous' => $yesterdayRates,
        ];
    }

    /**
     * Format exchange rates collection to simple array
     *
     * @param Collection $rates
     * @return array [currency_code => actual_rate]
     */
    protected function formatRates($rates): array
    {
        if ($rates instanceof Collection) {
            $rates = $rates->all();
        }

        if (empty($rates)) {
            return [];
        }

        $formatted = [];

        foreach ($rates as $rate) {
            if (is_object($rate)) {
                $formatted[$rate->currency_code] = $rate->actual_rate ?? ($rate->rate / $rate->nominal);
            }
        }

        return $formatted;
    }

    /**
     * Check if we have recent data (within last 24 hours)
     *
     * @return bool
     */
    public function hasRecentData(): bool
    {
        $latestDate = ExchangeRate::max('rate_date');

        if (!$latestDate) {
            return false;
        }

        $latest = Carbon::parse($latestDate);
        return $latest->isToday() || $latest->isYesterday();
    }

    /**
     * Get the date of the latest rates
     *
     * @return string|null
     */
    public function getLatestRateDate(): ?string
    {
        return ExchangeRate::max('rate_date');
    }
}
