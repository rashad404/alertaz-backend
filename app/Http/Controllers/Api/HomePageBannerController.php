<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomePageBanner;
use App\Models\Language;
use Illuminate\Http\Request;

class HomePageBannerController extends Controller
{
    public function index($locale = null)
    {
        // Əgər locale verilməzsə, əsas dili al
        if (!$locale) {
            $mainLanguage = Language::where('is_main', true)->first();
            $locale = $mainLanguage ? $mainLanguage->lang_code : 'az';
        }

        // Dili yoxla
        $language = Language::where('lang_code', $locale)
            ->where('status', true)
            ->first();

        if (!$language) {
            return response()->json([
                'success' => false,
                'message' => 'Dil tapılmadı'
            ], 404);
        }

        // Banner-ları al və hər birinə uyğun dilə görə image əlavə et
        $banners = HomePageBanner::all()->map(function ($banner) use ($locale) {
            $bannerData = [
                'id' => $banner->id,
                'link' => $banner->link,
            ];

            // Uyğun dilə görə banner image əlavə et
            if (isset($banner->banner_image[$locale])) {
                $bannerData['banner_image'] = asset('storage/' . $banner->banner_image[$locale]);
            } else {
                $bannerData['banner_image'] = null;
            }

            return $bannerData;
        });

        return response()->json([
            'success' => true,
            'data' => $banners,
            'locale' => $locale
        ]);
    }
}
