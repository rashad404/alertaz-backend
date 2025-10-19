<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\Category;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(Request $request, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $perPage = $request->get('per_page', 9);
        $categorySlug = $request->get('category');
        $tag = $request->get('tag');
        $companyId = $request->get('company_id');
        $lang = $lang ?? app()->getLocale();
        
        $query = News::with('category')
            ->where('language', $lang)
            ->where('status', true)
            ->where('publish_date', '<=', now());
        
        // Filter by company if provided
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        
        // Filter by category if provided
        if ($categorySlug) {
            $category = Category::where('slug', $categorySlug)->first();
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }
        
        // Filter by hashtag if provided
        if ($tag) {
            // Since hashtags is stored as JSON array, use JSON search
            $query->whereJsonContains('hashtags', $tag);
        }
        
        $news = $query->orderBy('publish_date', 'desc')
            ->paginate($perPage);
        
        $news->getCollection()->transform(function ($item) use ($lang) {
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
                'id' => $item->id,
                'language' => $item->language,
                'title' => $item->title,
                'sub_title' => $item->sub_title,
                'slug' => $item->slug,
                'body' => $item->body,
                'thumbnail_image' => $item->thumbnail_image ? asset('storage/' . $item->thumbnail_image) : null,
                // Return publish_date in Azerbaijan time without UTC conversion
                'publish_date' => $item->publish_date ? \Carbon\Carbon::parse($item->publish_date)->format('Y-m-d\TH:i:s') : null,
                'views' => $item->views,
                'author' => $item->author,
                'hashtags' => $item->hashtags,
                'category' => $item->category ? [
                    'id' => $item->category->id,
                    'title' => $getTranslation($item->category->title),
                    'slug' => $item->category->slug,
                ] : null,
                'seo_title' => $item->seo_title,
                'seo_keywords' => $item->seo_keywords,
                'seo_description' => $item->seo_description,
            ];
        });
        
        return response()->json($news);
    }
    
    private function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    public function byCategory($lang = null, $categorySlug = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        $perPage = request()->get('per_page', 9);
        
        $query = News::where('status', true)
            ->where('language', $lang)
            ->where('publish_date', '<=', now());
            
        $category = null;
        if ($categorySlug) {
            $category = Category::where('slug', $categorySlug)->first();
            if (!$category) {
                return response()->json(['message' => 'Category not found'], 404);
            }
            $query->where('category_id', $category->id);
        }
        
        $news = $query->orderBy('publish_date', 'desc')->paginate($perPage);
        
        // Helper function to get translation from JSON for categories
        $getTranslation = function($field, $lang) {
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
        
        $news->getCollection()->transform(function ($item) use ($lang, $getTranslation) {
            return [
                'id' => $item->id,
                'language' => $item->language,
                'title' => $item->title,
                'sub_title' => $item->sub_title,
                'slug' => $item->slug,
                'thumbnail_image' => $item->thumbnail_image ? asset('storage/' . $item->thumbnail_image) : null,
                // Return publish_date in Azerbaijan time without UTC conversion
                'publish_date' => $item->publish_date ? \Carbon\Carbon::parse($item->publish_date)->format('Y-m-d\TH:i:s') : null,
                'body' => $item->body,
                'views' => $item->views,
                'author' => $item->author,
                'hashtags' => $item->hashtags,
                'category' => $item->category ? [
                    'id' => $item->category->id,
                    'title' => $getTranslation($item->category->title, $lang),
                    'slug' => $item->category->slug,
                ] : null,
            ];
        });
        return response()->json($news);
    }

    public function similarNews($categoryId, $newsId, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        $news = News::where('category_id', $categoryId)
            ->where('id', '!=', $newsId)
            ->where('language', $lang)
            ->where('status', true)
            ->where('publish_date', '<=', now())
            ->orderBy('publish_date', 'desc')
            ->limit(9)
            ->get();
        
        // Helper function to get translation from JSON for categories
        $getTranslation = function($field, $lang) {
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
        
        $news = $news->map(function ($item) use ($lang, $getTranslation) {
            return [
                'id' => $item->id,
                'language' => $item->language,
                'title' => $item->title,
                'sub_title' => $item->sub_title,
                'slug' => $item->slug,
                'body' => $item->body,
                'thumbnail_image' => $item->thumbnail_image ? asset('storage/' . $item->thumbnail_image) : null,
                // Return publish_date in Azerbaijan time without UTC conversion
                'publish_date' => $item->publish_date ? \Carbon\Carbon::parse($item->publish_date)->format('Y-m-d\TH:i:s') : null,
                'views' => $item->views,
                'author' => $item->author,
                'hashtags' => $item->hashtags,
                'category' => [
                    'id' => $item->category->id,
                    'title' => $getTranslation($item->category->title, $lang),
                    'slug' => $item->category->slug,
                ],
                'seo_title' => $item->seo_title,
                'seo_keywords' => $item->seo_keywords,
                'seo_description' => $item->seo_description,
            ];
        });
        
        return response()->json($news);
    }

    public function categories($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        $categories = Category::where('status', true)
            ->orderBy('order')
            ->get()
            ->map(function ($category) use ($lang) {
                // Helper function to get translation from JSON
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
                    'id' => $category->id,
                    'title' => $getTranslation($category->title),
                    'slug' => $category->slug,
                ];
            });
        return response()->json($categories);
    }

    public function show($lang = null, $slug)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        // Fetch news by slug only, not by language
        // News content stays in its original language regardless of site language
        $news = News::with('category')
            ->where('slug', $slug)
            ->where('publish_date', '<=', now())
            ->first();
        
        if (!$news) {
            return response()->json(['message' => 'News not found'], 404);
        }
        
        // Increment views
        $news->increment('views');
        
        // Use the news's language for category translation, not the site language
        $newsLang = $news->language;
        
        // Helper function to get translation from JSON for category
        $getTranslation = function($field) use ($newsLang) {
            if (!$field) return null;
            
            // If it's already a string and not JSON, return as is
            if (is_string($field) && !$this->isJson($field)) {
                return $field;
            }
            
            // Decode JSON
            $decoded = is_string($field) ? json_decode($field, true) : $field;
            
            if (is_array($decoded)) {
                // Return the translation for the news language or fallback
                return $decoded[$newsLang] ?? $decoded['az'] ?? reset($decoded);
            }
            
            return $field;
        };
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $news->id,
                'language' => $news->language,
                'title' => $news->title,
                'sub_title' => $news->sub_title,
                'slug' => $news->slug,
                'body' => $news->body,
                'thumbnail_image' => $news->thumbnail_image ? asset('storage/' . $news->thumbnail_image) : null,
                // Return publish_date in Azerbaijan time without UTC conversion
                'publish_date' => $news->publish_date ? \Carbon\Carbon::parse($news->publish_date)->format('Y-m-d\TH:i:s') : null,
                'views' => $news->views,
                'author' => $news->author,
                'hashtags' => $news->hashtags,
                'category' => $news->category ? [
                    'id' => $news->category->id,
                    'title' => $getTranslation($news->category->title),
                    'slug' => $news->category->slug,
                ] : null,
                'seo_title' => $news->seo_title,
                'seo_keywords' => $news->seo_keywords,
                'seo_description' => $news->seo_description,
            ]
        ]);
    }
}