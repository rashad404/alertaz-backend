<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AboutPageDynamicData;

class AboutPageDynamicDataController extends Controller
{
    public function index($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }

        $items = AboutPageDynamicData::where('status', true)
            ->orderBy('order')
            ->get()
            ->map(function ($item) use ($lang) {
                $lang = $lang ?? app()->getLocale();
                return [
                    'id' => $item->id,
                    'title' => $item->getTranslation('title', $lang),
                    'subtitle' => $item->getTranslation('subtitle', $lang),
                    'desc' => $item->getTranslation('desc', $lang),
                ];
            });

        return response()->json($items);
    }
}
