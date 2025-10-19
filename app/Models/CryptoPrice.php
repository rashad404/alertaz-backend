<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CryptoPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'coin_id',
        'symbol',
        'name',
        'image',
        'current_price',
        'market_cap',
        'market_cap_rank',
        'total_volume',
        'high_24h',
        'low_24h',
        'price_change_24h',
        'price_change_percentage_24h',
        'price_change_percentage_1h',
        'price_change_percentage_7d',
        'price_change_percentage_30d',
        'circulating_supply',
        'total_supply',
        'max_supply',
        'sparkline_7d',
        'popular_in_azerbaijan',
        'last_updated',
    ];

    protected $casts = [
        'sparkline_7d' => 'array',
        'popular_in_azerbaijan' => 'boolean',
        'last_updated' => 'datetime',
        'current_price' => 'decimal:8',
        'market_cap' => 'decimal:2',
        'total_volume' => 'decimal:2',
        'high_24h' => 'decimal:8',
        'low_24h' => 'decimal:8',
        'price_change_24h' => 'decimal:8',
        'price_change_percentage_24h' => 'decimal:4',
        'price_change_percentage_1h' => 'decimal:4',
        'price_change_percentage_7d' => 'decimal:4',
        'price_change_percentage_30d' => 'decimal:4',
        'circulating_supply' => 'decimal:2',
        'total_supply' => 'decimal:2',
        'max_supply' => 'decimal:2',
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
            return $symbol . number_format($marketCap / 1e12, 2) . ' trln';
        } elseif ($marketCap >= 1e9) {
            return $symbol . number_format($marketCap / 1e9, 2) . ' mlrd';
        } elseif ($marketCap >= 1e6) {
            return $symbol . number_format($marketCap / 1e6, 2) . ' mln';
        }
        
        return $symbol . number_format($marketCap, 2);
    }

    /**
     * Scope for popular coins in Azerbaijan
     */
    public function scopePopularInAzerbaijan($query)
    {
        return $query->where('popular_in_azerbaijan', true);
    }

    /**
     * Scope for ordering by market cap rank
     */
    public function scopeOrderByRank($query)
    {
        return $query->orderBy('market_cap_rank', 'asc');
    }
}