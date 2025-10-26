<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'name',
        'exchange',
        'current_price',
        'open',
        'high',
        'low',
        'previous_close',
        'change',
        'change_percent',
        'volume',
        'market_cap',
        'market_cap_rank',
        'fifty_two_week_high',
        'fifty_two_week_low',
        'last_updated',
    ];

    protected $casts = [
        'last_updated' => 'datetime',
        'current_price' => 'decimal:4',
        'open' => 'decimal:4',
        'high' => 'decimal:4',
        'low' => 'decimal:4',
        'previous_close' => 'decimal:4',
        'change' => 'decimal:4',
        'change_percent' => 'decimal:4',
        'market_cap' => 'decimal:2',
        'fifty_two_week_high' => 'decimal:4',
        'fifty_two_week_low' => 'decimal:4',
    ];

    /**
     * Get formatted price for display
     */
    public function getFormattedPrice($currency = 'usd')
    {
        $price = $this->current_price;

        if ($currency === 'azn') {
            $price = $price * 1.7; // USD to AZN conversion
            return '₼' . number_format($price, 2);
        }

        return '$' . number_format($price, 2);
    }

    /**
     * Get formatted market cap
     */
    public function getFormattedMarketCap($currency = 'usd')
    {
        $marketCap = $this->market_cap;

        if ($currency === 'azn') {
            $marketCap = $marketCap * 1.7;
            $symbol = '₼';
        } else {
            $symbol = '$';
        }

        if ($marketCap >= 1e12) {
            return $symbol . number_format($marketCap / 1e12, 2) . ' T';
        } elseif ($marketCap >= 1e9) {
            return $symbol . number_format($marketCap / 1e9, 2) . ' B';
        } elseif ($marketCap >= 1e6) {
            return $symbol . number_format($marketCap / 1e6, 2) . ' M';
        }

        return $symbol . number_format($marketCap, 2);
    }

    /**
     * Scope for ordering by market cap rank
     */
    public function scopeOrderByRank($query)
    {
        return $query->orderBy('market_cap_rank', 'asc');
    }

    /**
     * Scope for NYSE stocks
     */
    public function scopeNyse($query)
    {
        return $query->where('exchange', 'NYSE');
    }

    /**
     * Scope for NASDAQ stocks
     */
    public function scopeNasdaq($query)
    {
        return $query->where('exchange', 'NASDAQ');
    }

    /**
     * Check if price is up
     */
    public function isPriceUp(): bool
    {
        return $this->change > 0;
    }

    /**
     * Check if price is down
     */
    public function isPriceDown(): bool
    {
        return $this->change < 0;
    }
}
