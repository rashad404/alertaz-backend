<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class MetaTag extends Model
{
    use HasTranslations;

    protected $fillable = [
        'seo_title',
        'seo_keywords',
        'seo_description',
        'code',
    ];

    public array $translatable = [
        'seo_title',
        'seo_keywords',
        'seo_description',
    ];
}
