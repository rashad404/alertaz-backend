<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomePageBanner extends Model
{
    protected $fillable = [
        'banner_image',
        'link',
    ];

    protected $casts = [
        'banner_image' => 'array',
    ];
}
