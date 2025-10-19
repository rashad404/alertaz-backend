<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class FaqPageContent extends Model
{
    use HasTranslations;

    protected $fillable = [
        'title',
        'description',
        'image',
        'image_alt_text',
    ];

    public array $translatable = [
        'title',
        'description',
        'image_alt_text',
    ];
}
