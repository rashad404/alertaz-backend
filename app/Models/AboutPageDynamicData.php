<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class AboutPageDynamicData extends Model
{
    use HasTranslations;

    protected $fillable = [
        'title',
        'subtitle',
        'desc',
        'order',
        'status',
    ];

    public array $translatable = [
        'title',
        'subtitle',
        'desc',
    ];

    // Removed sortable configuration - was from Nova package

    protected $casts = [
        'status' => 'boolean',
    ];
}
