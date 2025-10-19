<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteAd;
use Illuminate\Http\Request;

class SiteAdController extends Controller
{
    public function index(Request $request)
    {
        $ads = SiteAd::where('is_active', true)
            ->orderBy('order')
            ->get()
            ->map(function ($ad) {
                return [
                    'id' => $ad->id,
                    'iframe' => $ad->iframe,
                    'image' => $ad->image ? asset('storage/' . $ad->image) : null,
                    'url' => $ad->url,
                    'place' => $ad->place,
                    'order' => $ad->order,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $ads
        ]);
    }
} 