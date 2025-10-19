<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Faq extends Model
{
    use HasTranslations;

    protected $fillable = [
        'question',
        'answer',
        'order',
        'status',
    ];

    public array $translatable = [
        'question',
        'answer',
    ];

    // Removed sortable configuration - was from Nova package

    protected $casts = [
        'status' => 'boolean',
    ];
}
