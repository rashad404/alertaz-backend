<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppDownloadSection;

class AppDownloadSectionController extends Controller
{
    public function show($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $section = AppDownloadSection::firstOrCreate([
            'title' => 'Download Our Mobile App',
            'description' => 'Get the best credit offers on your mobile device',
        ]);
        
        $lang = $lang ?? app()->getLocale();
        
        return response()->json([
            'id' => $section->id,
            'title' => $section->title,
            'description' => $section->description,
            'image' => $section->image ? asset('storage/' . $section->image) : null,
            'image_alt_text' => $section->getTranslation('image_alt_text', $lang),
            'app_store_url' => $section->app_store_url,
            'play_store_url' => $section->play_store_url,
        ]);
    }
} 