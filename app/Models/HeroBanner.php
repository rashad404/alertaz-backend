<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class HeroBanner extends Model
{
    use HasTranslations;

    protected $fillable = [
        'title',
        'description',
        'image',
        'link',
        'link_text',
        'order',
        'is_active',
    ];

    public array $translatable = [
        'title',
        'description',
        'link_text',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}