<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OffersCategory;

class OffersCategoryController extends Controller
{
    public function index($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $categories = OffersCategory::where('status', true)
            ->orderBy('order')
            ->get()
            ->map(function ($category) use ($lang) {
                $lang = $lang ?? app()->getLocale();
                return [
                    'id' => $category->id,
                    'title' => $category->getTranslation('title', $lang),
                    'slug' => $category->slug,
                ];
            });
            
        return response()->json($categories);
    }
} 