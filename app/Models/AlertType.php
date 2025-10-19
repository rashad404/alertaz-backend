<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class AlertType extends Model
{
    use HasFactory, HasTranslations;

    public $translatable = ['name', 'description'];

    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon',
        'configuration_schema',
        'condition_fields',
        'data_source',
        'check_interval',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'configuration_schema' => 'array',
        'condition_fields' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the personal alerts for this type.
     */
    public function personalAlerts()
    {
        return $this->hasMany(PersonalAlert::class);
    }

    /**
     * Scope for active alert types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Get available assets for this alert type.
     */
    public function getAvailableAssets()
    {
        // This can be overridden per type
        switch ($this->slug) {
            case 'crypto':
                return [
                    'BTC' => 'Bitcoin',
                    'ETH' => 'Ethereum',
                    'BNB' => 'Binance Coin',
                    'ADA' => 'Cardano',
                    'SOL' => 'Solana',
                    'XRP' => 'Ripple',
                    'DOT' => 'Polkadot',
                    'DOGE' => 'Dogecoin',
                    'AVAX' => 'Avalanche',
                    'MATIC' => 'Polygon',
                ];
            case 'stock':
                return [
                    'AAPL' => 'Apple Inc.',
                    'GOOGL' => 'Alphabet Inc.',
                    'MSFT' => 'Microsoft',
                    'AMZN' => 'Amazon',
                    'TSLA' => 'Tesla',
                    'META' => 'Meta Platforms',
                    'NVDA' => 'NVIDIA',
                ];
            case 'currency':
                return [
                    'USD/AZN' => 'US Dollar to Manat',
                    'EUR/AZN' => 'Euro to Manat',
                    'GBP/AZN' => 'British Pound to Manat',
                    'RUB/AZN' => 'Russian Ruble to Manat',
                    'TRY/AZN' => 'Turkish Lira to Manat',
                ];
            default:
                return [];
        }
    }

    /**
     * Get available operators for conditions.
     */
    public function getAvailableOperators()
    {
        return [
            'equals' => ['label' => 'Equals', 'symbol' => '='],
            'greater' => ['label' => 'Greater than', 'symbol' => '>'],
            'greater_equal' => ['label' => 'Greater or equal', 'symbol' => '>='],
            'less' => ['label' => 'Less than', 'symbol' => '<'],
            'less_equal' => ['label' => 'Less or equal', 'symbol' => '<='],
            'not_equals' => ['label' => 'Not equals', 'symbol' => '!='],
        ];
    }
}