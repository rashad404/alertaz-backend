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
                    // Top 100 US stocks by market cap
                    'AAPL' => 'Apple Inc.',
                    'MSFT' => 'Microsoft Corporation',
                    'GOOGL' => 'Alphabet Inc. (Class A)',
                    'AMZN' => 'Amazon.com Inc.',
                    'NVDA' => 'NVIDIA Corporation',
                    'META' => 'Meta Platforms Inc.',
                    'TSLA' => 'Tesla Inc.',
                    'BRK.B' => 'Berkshire Hathaway Inc.',
                    'V' => 'Visa Inc.',
                    'UNH' => 'UnitedHealth Group Inc.',
                    'XOM' => 'Exxon Mobil Corporation',
                    'JNJ' => 'Johnson & Johnson',
                    'WMT' => 'Walmart Inc.',
                    'JPM' => 'JPMorgan Chase & Co.',
                    'MA' => 'Mastercard Inc.',
                    'PG' => 'Procter & Gamble Co.',
                    'AVGO' => 'Broadcom Inc.',
                    'HD' => 'The Home Depot Inc.',
                    'CVX' => 'Chevron Corporation',
                    'MRK' => 'Merck & Co. Inc.',
                    'ABBV' => 'AbbVie Inc.',
                    'KO' => 'The Coca-Cola Company',
                    'PEP' => 'PepsiCo Inc.',
                    'COST' => 'Costco Wholesale Corporation',
                    'ADBE' => 'Adobe Inc.',
                    'MCD' => 'McDonald\'s Corporation',
                    'CSCO' => 'Cisco Systems Inc.',
                    'TMO' => 'Thermo Fisher Scientific Inc.',
                    'ACN' => 'Accenture plc',
                    'LLY' => 'Eli Lilly and Company',
                    'NFLX' => 'Netflix Inc.',
                    'NKE' => 'Nike Inc.',
                    'ABT' => 'Abbott Laboratories',
                    'ORCL' => 'Oracle Corporation',
                    'CRM' => 'Salesforce Inc.',
                    'DHR' => 'Danaher Corporation',
                    'VZ' => 'Verizon Communications Inc.',
                    'INTC' => 'Intel Corporation',
                    'TXN' => 'Texas Instruments Inc.',
                    'WFC' => 'Wells Fargo & Company',
                    'PM' => 'Philip Morris International Inc.',
                    'DIS' => 'The Walt Disney Company',
                    'NEE' => 'NextEra Energy Inc.',
                    'CMCSA' => 'Comcast Corporation',
                    'UPS' => 'United Parcel Service Inc.',
                    'BMY' => 'Bristol-Myers Squibb Company',
                    'HON' => 'Honeywell International Inc.',
                    'UNP' => 'Union Pacific Corporation',
                    'T' => 'AT&T Inc.',
                    'LOW' => 'Lowe\'s Companies Inc.',
                    'IBM' => 'International Business Machines',
                    'QCOM' => 'Qualcomm Inc.',
                    'BA' => 'The Boeing Company',
                    'AMD' => 'Advanced Micro Devices Inc.',
                    'AMGN' => 'Amgen Inc.',
                    'SPGI' => 'S&P Global Inc.',
                    'ELV' => 'Elevance Health Inc.',
                    'INTU' => 'Intuit Inc.',
                    'RTX' => 'Raytheon Technologies Corp.',
                    'BLK' => 'BlackRock Inc.',
                    'CAT' => 'Caterpillar Inc.',
                    'GE' => 'General Electric Company',
                    'PLD' => 'Prologis Inc.',
                    'DE' => 'Deere & Company',
                    'AXP' => 'American Express Company',
                    'SBUX' => 'Starbucks Corporation',
                    'GILD' => 'Gilead Sciences Inc.',
                    'NOW' => 'ServiceNow Inc.',
                    'MDLZ' => 'Mondelez International Inc.',
                    'ISRG' => 'Intuitive Surgical Inc.',
                    'TJX' => 'The TJX Companies Inc.',
                    'SYK' => 'Stryker Corporation',
                    'ADP' => 'Automatic Data Processing Inc.',
                    'BKNG' => 'Booking Holdings Inc.',
                    'ADI' => 'Analog Devices Inc.',
                    'MMC' => 'Marsh & McLennan Companies',
                    'REGN' => 'Regeneron Pharmaceuticals Inc.',
                    'CI' => 'Cigna Corporation',
                    'ZTS' => 'Zoetis Inc.',
                    'MO' => 'Altria Group Inc.',
                    'CVS' => 'CVS Health Corporation',
                    'C' => 'Citigroup Inc.',
                    'PGR' => 'The Progressive Corporation',
                    'VRTX' => 'Vertex Pharmaceuticals Inc.',
                    'DUK' => 'Duke Energy Corporation',
                    'SO' => 'The Southern Company',
                    'CB' => 'Chubb Limited',
                    'BDX' => 'Becton Dickinson and Company',
                    'SCHW' => 'The Charles Schwab Corporation',
                    'ETN' => 'Eaton Corporation plc',
                    'BSX' => 'Boston Scientific Corporation',
                    'AON' => 'Aon plc',
                    'ITW' => 'Illinois Tool Works Inc.',
                    'MMM' => '3M Company',
                    'HUM' => 'Humana Inc.',
                    'TGT' => 'Target Corporation',
                    'LRCX' => 'Lam Research Corporation',
                    'MU' => 'Micron Technology Inc.',
                    'PANW' => 'Palo Alto Networks Inc.',
                    'EQIX' => 'Equinix Inc.',
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