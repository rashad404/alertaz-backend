<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $categories = \App\Models\Category::where('status', true)
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
