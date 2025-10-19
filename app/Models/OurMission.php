<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class OurMission extends Model
{
    use HasTranslations;

    protected $fillable = [
        'title',
        'description',
        'order',
        'status',
    ];

    public array $translatable = [
        'title',
        'description',
    ];

    // Removed sortable configuration - was from Nova package

    protected $casts = [
        'status' => 'boolean',
    ];
}
