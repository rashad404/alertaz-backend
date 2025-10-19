<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Insurance extends Model
{
    use HasTranslations;

    protected $fillable = [
        'category_id',
        'provider_id',
        'title',
        'slug',
        'description',
        'coverage_amount',
        'premium',
        'duration',
        'features',
        'requirements',
        'documents',
        'exclusions',
        'image',
        'order',
        'views',
        'is_featured',
        'status',
        'seo_title',
        'seo_keywords',
        'seo_description'
    ];

    public $translatable = [
        'title',
        'description',
        'coverage_amount',
        'premium',
        'duration',
        'features',
        'requirements',
        'documents',
        'exclusions',
        'seo_title',
        'seo_keywords',
        'seo_description'
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_featured' => 'boolean',
        'features' => 'array',
        'requirements' => 'array',
        'documents' => 'array',
        'exclusions' => 'array',
        'coverage_amount' => 'array',
        'premium' => 'array',
        'duration' => 'array'
    ];

    public function category()
    {
        return $this->belongsTo(InsuranceCategory::class, 'category_id');
    }

    public function provider()
    {
        return $this->belongsTo(InsuranceProvider::class, 'provider_id');
    }

    public function advantages()
    {
        return $this->hasMany(InsuranceAdvantage::class);
    }
}