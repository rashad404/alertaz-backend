<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'currency_code',
        'currency_name',
        'rate',
        'nominal',
        'rate_date',
        'source',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'nominal' => 'decimal:2',
        'rate_date' => 'date',
    ];

    /**
     * Get the latest rates for all currencies
     */
    public static function getLatestRates()
    {
        $latestDate = self::max('rate_date');
        return self::where('rate_date', $latestDate)->get();
    }

    /**
     * Get rate for a specific currency on a specific date
     */
    public static function getRateForCurrency($currencyCode, $date = null)
    {
        if (!$date) {
            $date = self::max('rate_date');
        }
        
        return self::where('currency_code', $currencyCode)
            ->where('rate_date', $date)
            ->first();
    }

    /**
     * Get the actual exchange rate (considering nominal)
     */
    public function getActualRateAttribute()
    {
        return $this->rate / $this->nominal;
    }
}