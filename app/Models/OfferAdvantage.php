<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class OfferAdvantage extends Model
{
    use HasTranslations;

    // Removed sortable configuration - was from Nova package

    protected $fillable = [
        'title',
        'offer_id',
        'order',
        'status',
    ];

    public array $translatable = [
        'title',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }
} 