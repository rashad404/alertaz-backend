<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroBanner;

class HomePageSliderBannerController extends Controller
{
    public function index($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $banners = HeroBanner::active()
            ->ordered()
            ->get()
            ->map(function ($banner) use ($lang) {
                $lang = $lang ?? app()->getLocale();
                
                return [
                    'id' => $banner->id,
                    'title' => $banner->getTranslation('title', $lang),
                    'description' => $banner->getTranslation('description', $lang),
                    'image' => $banner->image,
                    'link' => $banner->link,
                    'link_text' => $banner->getTranslation('link_text', $lang),
                    'order' => $banner->order,
                ];
            });
        
        return response()->json(['data' => $banners]);
    }
}