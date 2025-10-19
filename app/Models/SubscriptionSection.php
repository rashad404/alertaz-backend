<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class SubscriptionSection extends Model
{
    use HasTranslations;

    protected $fillable = [
        'image',
        'image_alt_text',
        'title',
        'description',
    ];

    public $translatable = [
        'image_alt_text',
        'title',
        'description',
    ];
}
