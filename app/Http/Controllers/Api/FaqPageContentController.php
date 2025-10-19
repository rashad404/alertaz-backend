<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaqPageContent;

class FaqPageContentController extends Controller
{
    public function index($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }

        $faqPage = FaqPageContent::firstOrCreate([]);
        $lang = $lang ?? app()->getLocale();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $faqPage->id,
                'title' => $faqPage->getTranslation('title', $lang),
                'description' => $faqPage->getTranslation('description', $lang),
                'image' => $faqPage->image ? asset('storage/' . $faqPage->image) : null,
                'image_alt_text' => $faqPage->getTranslation('image_alt_text', $lang),
            ]
        ]);
    }
}
