<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MetaTag;
use Illuminate\Http\Request;

class MetaTagController extends Controller
{
    public function showByCode($lang = null, $code)
    {
        $meta = MetaTag::where('code', $code)->first();
        if (!$meta) {
            return response()->json(['message' => 'Meta tag not found'], 404);
        }
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        return response()->json([
            'seo_title' => $meta->getTranslation('seo_title', $lang),
            'seo_keywords' => $meta->getTranslation('seo_keywords', $lang),
            'seo_description' => $meta->getTranslation('seo_description', $lang),
            'code' => $meta->code,
        ]);
    }
} 