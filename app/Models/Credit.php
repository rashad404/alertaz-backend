<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Credit extends Model
{
    use HasFactory, HasTranslations;

    protected $fillable = [
        'credit_type_id',
        'bank_name',
        'credit_name',
        'credit_image',
        'about',
        'credit_amount',
        'credit_term',
        'interest_rate',
        'guarantor',
        'collateral',
        'method_of_purchase',
        'views',
        'seo_title',
        'seo_keywords',
        'seo_description',
        'status',
        'order',
        'min_amount',
        'max_amount',
        'min_term_months',
        'max_term_months',
        'commission_rate',
        'bank_phone',
        'bank_address',
    ];

    protected $casts = [
        'about' => 'array',
        'seo_title' => 'array',
        'seo_keywords' => 'array',
        'seo_description' => 'array',
    ];

    // Removed sortable configuration - was from Nova package

    public array $translatable = [
        'credit_name',
        'about',
        'guarantor',
        'collateral',
        'method_of_purchase',
        'seo_title',
        'seo_keywords',
        'seo_description',
    ];

    /**
     * Get the credit type that owns the credit
     */
    public function creditType()
    {
        return $this->belongsTo(CreditType::class);
    }
} 