<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class OffersCategory extends Model
{
    use HasTranslations;

    // Removed sortable configuration - was from Nova package

    protected $fillable = [
        'title',
        'slug',
        'order',
        'status',
    ];

    protected $attributes = [
        'status' => true,
    ];

    public array $translatable = [
        'title',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function offers()
    {
        return $this->hasMany(Offer::class, 'category_id');
    }
} 