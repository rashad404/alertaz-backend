<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use HasTranslations;
    
    protected $table = 'news_categories';
    
    // Removed sortable configuration - was from Nova package
    protected $fillable = [
        'title',
        'slug',
        'seo_title',
        'seo_keywords',
        'seo_description',
        'order',
        'status',
    ];

    protected $attributes = [
        'status' => true,
    ];

    public array $translatable = [
        'title',
        'seo_title',
        'seo_keywords',
        'seo_description',
    ];

    public function news()
    {
        return $this->hasMany(News::class, 'category_id');
    }
}
