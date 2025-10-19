<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guide;
use Illuminate\Http\Request;

class GuideController extends Controller
{
    /**
     * Get all guides grouped by category
     */
    public function index(Request $request)
    {
        $locale = $request->header('Accept-Language', 'az');
        
        $guides = Guide::published()
            ->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($guide) use ($locale) {
                return [
                    'id' => $guide->id,
                    'slug' => $guide->slug,
                    'title' => $guide->getTranslation('title', $locale),
                    'description' => $guide->getTranslation('description', $locale),
                    'category' => $guide->category,
                    'read_time' => $guide->read_time,
                    'difficulty' => $guide->difficulty,
                    'views' => $guide->views,
                    'is_featured' => $guide->is_featured,
                ];
            })
            ->groupBy('category');

        // Get popular guides
        $popularGuides = Guide::published()
            ->orderBy('views', 'desc')
            ->limit(4)
            ->get()
            ->map(function ($guide) use ($locale) {
                return [
                    'id' => $guide->id,
                    'slug' => $guide->slug,
                    'title' => $guide->getTranslation('title', $locale),
                    'views' => $guide->views,
                ];
            });

        return response()->json([
            'guides' => $guides,
            'popular' => $popularGuides,
        ]);
    }

    /**
     * Get guides by category
     */
    public function byCategory(Request $request, $category)
    {
        $locale = $request->header('Accept-Language', 'az');
        
        $guides = Guide::published()
            ->byCategory($category)
            ->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($guide) use ($locale) {
                return [
                    'id' => $guide->id,
                    'slug' => $guide->slug,
                    'title' => $guide->getTranslation('title', $locale),
                    'description' => $guide->getTranslation('description', $locale),
                    'read_time' => $guide->read_time,
                    'difficulty' => $guide->difficulty,
                    'views' => $guide->views,
                    'is_featured' => $guide->is_featured,
                ];
            });

        return response()->json($guides);
    }

    /**
     * Get single guide by slug
     */
    public function show(Request $request, $locale = null, $slug)
    {
        $locale = $locale ?: $request->header('Accept-Language', 'az');
        
        $guide = Guide::published()
            ->where('slug', $slug)
            ->firstOrFail();

        // Increment views
        $guide->incrementViews();

        return response()->json([
            'id' => $guide->id,
            'slug' => $guide->slug,
            'title' => $guide->getTranslation('title', $locale),
            'description' => $guide->getTranslation('description', $locale),
            'content' => $guide->getTranslation('content', $locale),
            'category' => $guide->category,
            'read_time' => $guide->read_time,
            'difficulty' => $guide->difficulty,
            'views' => $guide->views,
            'is_featured' => $guide->is_featured,
            'created_at' => $guide->created_at,
            'updated_at' => $guide->updated_at,
        ]);
    }

    /**
     * Get featured guides
     */
    public function featured(Request $request)
    {
        $locale = $request->header('Accept-Language', 'az');
        
        $guides = Guide::published()
            ->featured()
            ->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get()
            ->map(function ($guide) use ($locale) {
                return [
                    'id' => $guide->id,
                    'slug' => $guide->slug,
                    'title' => $guide->getTranslation('title', $locale),
                    'description' => $guide->getTranslation('description', $locale),
                    'category' => $guide->category,
                    'read_time' => $guide->read_time,
                ];
            });

        return response()->json($guides);
    }
}