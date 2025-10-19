<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomeSliderNews;

class HomeSliderNewsController extends Controller
{
    public function homePageSliderNews($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        // First try to get news using the new show_in_slider field
        $sliderNews = \App\Models\News::with('category')
            ->where('language', $lang)
            ->where('show_in_slider', true)
            ->where('status', true)
            ->orderBy('slider_order', 'asc')
            ->orderBy('publish_date', 'desc')
            ->limit(5)
            ->get();
        
        // If no news with show_in_slider, fall back to the old HomeSliderNews table
        if ($sliderNews->isEmpty()) {
            $sliderNews = HomeSliderNews::with(['news' => function($query) use ($lang) {
                $query->where('language', $lang);
            }, 'news.category'])
                ->orderBy('order')
                ->get()
                ->map(function ($slider) use ($lang) {
                    $news = $slider->news;
                    
                    // Helper function to get translation from JSON for category
                    $getTranslation = function($field) use ($lang) {
                        if (!$field) return null;
                        
                        // If it's already a string and not JSON, return as is
                        if (is_string($field) && !$this->isJson($field)) {
                            return $field;
                        }
                        
                        // Decode JSON
                        $decoded = is_string($field) ? json_decode($field, true) : $field;
                        
                        if (is_array($decoded)) {
                            // Return the translation for requested language or fallback
                            return $decoded[$lang] ?? $decoded['az'] ?? reset($decoded);
                        }
                        
                        return $field;
                    };
                    
                    return [
                        'id' => $slider->id,
                        'order' => $slider->order,
                        'news' => $news ? [
                            'id' => $news->id,
                            'slug' => $news->slug,
                            'title' => $news->title,
                            'views' => $news->views,
                            'author' => $news->author,
                            'hashtags' => $news->hashtags,
                            'body' => $news->body,
                            'category_id' => $news->category_id,
                            'category' => $news->category ? [
                                'id' => $news->category->id,
                                'title' => $getTranslation($news->category->title),
                                'slug' => $news->category->slug,
                            ] : null,
                            'thumbnail_image' => $news->thumbnail_image ? asset('storage/' . $news->thumbnail_image) : null,
                            'publish_date' => $news->publish_date,
                            'seo_title' => $news->seo_title,
                            'seo_keywords' => $news->seo_keywords,
                            'seo_description' => $news->seo_description,
                            'created_at' => $news->created_at,
                            'updated_at' => $news->updated_at,
                        ] : null,
                    ];
                })
                ->filter(function ($slider) {
                    return $slider['news'] !== null;
                })
                ->values();
        } else {
            // Format the news items from the new approach
            $sliderNews = $sliderNews->map(function ($news) use ($lang) {
                // Helper function to get translation from JSON for category
                $getTranslation = function($field) use ($lang) {
                    if (!$field) return null;
                    
                    // If it's already a string and not JSON, return as is
                    if (is_string($field) && !$this->isJson($field)) {
                        return $field;
                    }
                    
                    // Decode JSON
                    $decoded = is_string($field) ? json_decode($field, true) : $field;
                    
                    if (is_array($decoded)) {
                        // Return the translation for requested language or fallback
                        return $decoded[$lang] ?? $decoded['az'] ?? reset($decoded);
                    }
                    
                    return $field;
                };
                
                return [
                    'id' => $news->id,
                    'order' => $news->slider_order ?? 0,
                    'news' => [
                        'id' => $news->id,
                        'slug' => $news->slug,
                        'title' => $news->title,
                        'views' => $news->views,
                        'author' => $news->author,
                        'hashtags' => $news->hashtags,
                        'body' => $news->body,
                        'category_id' => $news->category_id,
                        'category' => $news->category ? [
                            'id' => $news->category->id,
                            'title' => $getTranslation($news->category->title),
                            'slug' => $news->category->slug,
                        ] : null,
                        'thumbnail_image' => $news->thumbnail_image ? asset('storage/' . $news->thumbnail_image) : null,
                        'publish_date' => $news->publish_date,
                        'seo_title' => $news->seo_title,
                        'seo_keywords' => $news->seo_keywords,
                        'seo_description' => $news->seo_description,
                        'created_at' => $news->created_at,
                        'updated_at' => $news->updated_at,
                    ],
                ];
            });
        }
            
        return response()->json($sliderNews);
    }
    
    private function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}