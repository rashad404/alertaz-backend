<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OurMission;

class OurMissionController extends Controller
{
    public function index($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }

        $items = OurMission::where('status', true)
            ->orderBy('order')
            ->get()
            ->map(function ($item) use ($lang) {
                $lang = $lang ?? app()->getLocale();
                return [
                    'id' => $item->id,
                    'title' => $item->getTranslation('title', $lang),
                    'description' => $item->getTranslation('description', $lang),
                ];
            });

        return response()->json($items);
    }
}
