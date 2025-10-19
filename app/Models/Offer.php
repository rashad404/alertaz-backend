<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Offer extends Model
{
    use HasTranslations;

    // Removed sortable configuration - was from Nova package

    protected $fillable = [
        'title',
        'annual_interest_rate',
        'duration_id',
        'monthly_payment',
        'min_amount',
        'max_amount',
        'site_link',
        'category_id',
        'order',
        'status',
        'bank_id',
        'loan_type',
        'max_duration',
        'min_interest_rate',
        'max_interest_rate',
        'employment_reference_required',
        'guarantor_required',
        'collateral_required',
        'note',
        'views',
    ];

    protected $attributes = [
        'status' => true,
    ];

    public array $translatable = [
        'title',
        'loan_type',
        'note',
    ];

    protected $casts = [
        'status' => 'boolean',
        'annual_interest_rate' => 'decimal:2',
        'monthly_payment' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
    ];

        public function category()
    {
        return $this->belongsTo(OffersCategory::class, 'category_id');
    }

    public function duration()
    {
        return $this->belongsTo(OffersDuration::class, 'duration_id');
    }

    public function advantages()
    {
        return $this->hasMany(OfferAdvantage::class);
    }

    public function bank()
    {
        return $this->belongsTo(Company::class, 'bank_id');
    }
    
    // Alias for backward compatibility
    public function company()
    {
        return $this->bank();
    }
} 