<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomePageAd extends Model
{
    protected $fillable = [
        'iframe',
        'image',
        'url',
        'place',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];
}
