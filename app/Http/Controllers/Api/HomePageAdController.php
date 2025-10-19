<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomePageAd;
use Illuminate\Http\Request;

class HomePageAdController extends Controller
{
    public function index()
    {
        $ads = HomePageAd::where('is_active', true)
            ->orderBy('order', 'asc')
            ->select(['id', 'iframe', 'image', 'url', 'place'])
            ->get();

        // Fix image paths to include /storage/ prefix
        $ads->transform(function ($ad) {
            if ($ad->image && !str_starts_with($ad->image, '/storage/') && !str_starts_with($ad->image, 'http')) {
                $ad->image = '/storage/' . $ad->image;
            }
            return $ad;
        });

        return response()->json([
            'success' => true,
            'data' => $ads
        ]);
    }

    public function getByPlace($place)
    {
        $ads = HomePageAd::where('place', $place)
            ->where('is_active', true)
            ->orderBy('order', 'asc')
            ->select(['id', 'iframe', 'image', 'url', 'place'])
            ->get();

        // Fix image paths to include /storage/ prefix
        $ads->transform(function ($ad) {
            if ($ad->image && !str_starts_with($ad->image, '/storage/') && !str_starts_with($ad->image, 'http')) {
                $ad->image = '/storage/' . $ad->image;
            }
            return $ad;
        });

        return response()->json([
            'success' => true,
            'data' => $ads
        ]);
    }
}
