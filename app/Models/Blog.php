<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Illuminate\Support\Str;

class Blog extends Model
{
    use HasTranslations;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'author',
        'tags',
        'reading_time',
        'views',
        'featured',
        'status',
        'published_at',
        'seo_title',
        'seo_keywords',
        'seo_description',
    ];

    public array $translatable = [
        'title',
        'excerpt',
        'content',
        'seo_title',
        'seo_keywords',
        'seo_description',
    ];

    protected $casts = [
        'tags' => 'array',
        'featured' => 'boolean',
        'status' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($blog) {
            // Auto-generate slug if not provided
            if (empty($blog->slug)) {
                $baseSlug = Str::slug($blog->getTranslation('title', 'en') ?: $blog->getTranslation('title', 'az'));
                $slug = $baseSlug;
                $count = 1;
                
                while (static::where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $count;
                    $count++;
                }
                
                $blog->slug = $slug;
            }
            
            // Set published_at if not provided
            if (empty($blog->published_at)) {
                $blog->published_at = now();
            }
            
            // Calculate reading time based on content length
            if (empty($blog->reading_time)) {
                $content = strip_tags($blog->getTranslation('content', 'az'));
                $wordCount = str_word_count($content);
                $blog->reading_time = max(1, ceil($wordCount / 200)); // Assuming 200 words per minute
            }
        });
    }

    // Scope for published blogs
    public function scopePublished($query)
    {
        return $query->where('status', true)
                     ->where('published_at', '<=', now());
    }

    // Scope for featured blogs
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }
}