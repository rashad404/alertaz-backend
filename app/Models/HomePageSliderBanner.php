<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomePageSliderBanner extends Model
{
    // use SortableTrait; // Removed - was from Nova package

    // Removed sortable configuration - was from Nova package

    protected $fillable = [
        'news_id',
        'order',
    ];

    public function news()
    {
        return $this->belongsTo(News::class);
    }
} 