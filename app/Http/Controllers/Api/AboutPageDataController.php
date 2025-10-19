<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AboutPageData;

class AboutPageDataController extends Controller
{
    public function show($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }

        $about = AboutPageData::firstOrCreate([]);
        $lang = $lang ?? app()->getLocale();

        return response()->json([
            'id' => $about->id,
            'title' => $about->getTranslation('title', $lang),
            'description' => $about->getTranslation('description', $lang),
            'image' => $about->image ? asset('storage/' . $about->image) : null,
            'image_alt_text' => $about->getTranslation('image_alt_text', $lang),
            'mission_section_title' => $about->getTranslation('mission_section_title', $lang),
            'video_image' => $about->video_image ? asset('storage/' . $about->video_image) : null,
            'video_link' => $about->video_link,
            'our_mission_title' => $about->getTranslation('our_mission_title', $lang),
            'our_mission_text' => $about->getTranslation('our_mission_text', $lang),
            'carer_section_title' => $about->getTranslation('carer_section_title', $lang),
            'carer_section_image' => $about->carer_section_image ? asset('storage/' . $about->carer_section_image) : null,
            'carer_section_image_alt_text' => $about->getTranslation('carer_section_image_alt_text', $lang),
            'carer_section_desc' => $about->getTranslation('carer_section_desc', $lang),
        ]);
    }
}
