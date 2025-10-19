<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class ImageSection extends Model
{
    use HasTranslations;

    protected $fillable = [
        'logo',
        'banner_image',
        'logo_alt_text',
    ];

    public $translatable = [
        'logo_alt_text',
    ];
} 