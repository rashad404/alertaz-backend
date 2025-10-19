<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    protected $guarded = [];

    protected $fillable = [
        'language',
        'title',
        'sub_title',
        'slug',
        'body',
        'category_id',
        'news_type',
        'publish_date',
        'seo_title',
        'seo_keywords',
        'seo_description',
        'thumbnail_image',
        'status',
        'show_in_slider',
        'slider_order',
        'author',
        'author_id',
        'company_id',
        'hashtags',
        'views',
        'is_ai_generated',
        'source_url'
    ];
    
    protected $attributes = [
        'status' => true,
        'language' => 'az',
    ];
    
    protected $casts = [
        'publish_date' => 'datetime',
        'status' => 'boolean',
        'show_in_slider' => 'boolean',
        'is_ai_generated' => 'boolean',
        'slider_order' => 'integer',
        'views' => 'integer',
        'hashtags' => 'array',
    ];
    
    protected static function booted()
    {
        static::creating(function ($news) {
            if (empty($news->publish_date)) {
                $news->publish_date = now();
            }
            
            // Auto-generate slug if not provided
            if (empty($news->slug)) {
                $baseSlug = \Str::slug($news->title);
                $slug = $baseSlug;
                $count = 1;
                
                while (static::where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $count;
                    $count++;
                }
                
                $news->slug = $slug;
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'news_category')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function primaryCategory()
    {
        return $this->belongsToMany(Category::class, 'news_category')
            ->wherePivot('is_primary', true)
            ->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
