<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Guide extends Model
{
    use HasTranslations;

    protected $fillable = [
        'slug',
        'title',
        'description',
        'content',
        'category',
        'read_time',
        'difficulty',
        'views',
        'is_featured',
        'is_published',
        'order',
    ];

    public $translatable = ['title', 'description', 'content'];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_published' => 'boolean',
        'views' => 'integer',
        'read_time' => 'integer',
        'order' => 'integer',
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function incrementViews()
    {
        $this->increment('views');
    }
}
