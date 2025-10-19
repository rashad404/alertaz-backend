<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class AppDownloadSection extends Model
{
    use HasTranslations;

    protected $fillable = [
        'title',
        'description',
        'image',
        'image_alt_text',
        'app_store_url',
        'play_store_url',
    ];

    public array $translatable = [
        'title',
        'description',
        'image_alt_text',
    ];
} 