<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $perPage = $request->get('per_page', 9);
        $tag = $request->get('tag');
        $featured = $request->get('featured');
        $search = $request->get('search');
        
        $query = Blog::published();
        
        // Filter by tag if provided
        if ($tag) {
            $query->whereJsonContains('tags', $tag);
        }
        
        // Filter by featured if requested
        if ($featured) {
            $query->featured();
        }
        
        // Search in title and content
        if ($search) {
            $query->where(function($q) use ($search, $lang) {
                $q->whereRaw("JSON_EXTRACT(title, '$.$lang') LIKE ?", ["%$search%"])
                  ->orWhereRaw("JSON_EXTRACT(content, '$.$lang') LIKE ?", ["%$search%"])
                  ->orWhereRaw("JSON_EXTRACT(excerpt, '$.$lang') LIKE ?", ["%$search%"]);
            });
        }
        
        $blogs = $query->orderBy('published_at', 'desc')
                       ->paginate($perPage);
        
        $lang = $lang ?? app()->getLocale();
        
        $blogs->getCollection()->transform(function ($blog) use ($lang) {
            return $this->transformBlog($blog, $lang);
        });
        
        return response()->json($blogs);
    }
    
    public function show($lang = null, $slug)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $blog = Blog::where('slug', $slug)
                    ->published()
                    ->first();
        
        if (!$blog) {
            return response()->json(['message' => 'Blog not found'], 404);
        }
        
        // Increment views
        $blog->increment('views');
        
        $lang = $lang ?? app()->getLocale();
        
        return response()->json([
            'success' => true,
            'data' => $this->transformBlog($blog, $lang, true)
        ]);
    }
    
    public function featured($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $blogs = Blog::published()
                     ->featured()
                     ->orderBy('published_at', 'desc')
                     ->limit(3)
                     ->get();
        
        $lang = $lang ?? app()->getLocale();
        
        $blogs = $blogs->map(function ($blog) use ($lang) {
            return $this->transformBlog($blog, $lang);
        });
        
        return response()->json($blogs);
    }
    
    public function related($lang = null, $slug)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $currentBlog = Blog::where('slug', $slug)->first();
        
        if (!$currentBlog) {
            return response()->json(['message' => 'Blog not found'], 404);
        }
        
        // Find related blogs based on tags
        $relatedQuery = Blog::published()
                           ->where('id', '!=', $currentBlog->id);
        
        if ($currentBlog->tags && count($currentBlog->tags) > 0) {
            $relatedQuery->where(function($q) use ($currentBlog) {
                foreach ($currentBlog->tags as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            });
        }
        
        $blogs = $relatedQuery->orderBy('published_at', 'desc')
                              ->limit(3)
                              ->get();
        
        // If not enough related blogs, get recent ones
        if ($blogs->count() < 3) {
            $additionalBlogs = Blog::published()
                                  ->where('id', '!=', $currentBlog->id)
                                  ->whereNotIn('id', $blogs->pluck('id'))
                                  ->orderBy('published_at', 'desc')
                                  ->limit(3 - $blogs->count())
                                  ->get();
            
            $blogs = $blogs->concat($additionalBlogs);
        }
        
        $lang = $lang ?? app()->getLocale();
        
        $blogs = $blogs->map(function ($blog) use ($lang) {
            return $this->transformBlog($blog, $lang);
        });
        
        return response()->json($blogs);
    }
    
    public function tags($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        // Get all unique tags from published blogs
        $allTags = Blog::published()
                       ->pluck('tags')
                       ->flatten()
                       ->unique()
                       ->filter()
                       ->values();
        
        return response()->json($allTags);
    }
    
    private function transformBlog($blog, $lang, $includeContent = false)
    {
        $data = [
            'id' => $blog->id,
            'title' => $blog->getTranslation('title', $lang),
            'slug' => $blog->slug,
            'excerpt' => $blog->getTranslation('excerpt', $lang),
            'featured_image' => $blog->featured_image ? asset('storage/' . $blog->featured_image) : null,
            'author' => $blog->author,
            'tags' => $blog->tags ?? [],
            'reading_time' => $blog->reading_time,
            'views' => $blog->views,
            'featured' => $blog->featured,
            'published_at' => $blog->published_at,
            'seo_title' => $blog->getTranslation('seo_title', $lang),
            'seo_keywords' => $blog->getTranslation('seo_keywords', $lang),
            'seo_description' => $blog->getTranslation('seo_description', $lang),
        ];
        
        if ($includeContent) {
            $data['content'] = $blog->getTranslation('content', $lang);
        }
        
        return $data;
    }
}