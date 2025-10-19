<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ImageSection;
use App\Models\HomePageBanner;
use App\Models\HomePageAd;

class SiteAssetsController extends Controller
{
    public function siteImages($locale = null)
    {
        if ($locale) {
            app()->setLocale($locale);
        }
        $locale = $locale ?? app()->getLocale();
        // Logo (first Image record)
        $imageSection = \App\Models\ImageSection::first();
        return response()->json([
            'logo' => $imageSection && $imageSection->logo ? asset('storage/' . $imageSection->logo) : null,
            'logo_alt_text' => $imageSection ? $imageSection->logo_alt_text : null,
        ]);
    }

    public function ads($locale = null)
    {
        if ($locale) {
            app()->setLocale($locale);
        }
        $locale = $locale ?? app()->getLocale();

        // HomePageBanners
        $banner = HomePageBanner::first();
        $bannerData = null;
        if ($banner) {
            $bannerData = [
                'id' => $banner->id,
                'banner_image' => isset($banner->banner_image[$locale]) ? asset('storage/' . $banner->banner_image[$locale]) : null,
                'link' => $banner->link,
            ];
        }

        // HomePageAds
        $ads = HomePageAd::where('is_active', true)
            ->orderBy('order', 'asc')
            ->select(['id', 'iframe', 'image', 'url', 'place'])
            ->get();

        return response()->json([
            'banner' => $bannerData,
            'ads' => $ads,
        ]);
    }
}
