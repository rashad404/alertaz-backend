<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;

class FaqController extends Controller
{
    public function index($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }

        $faqs = Faq::where('status', true)
            ->orderBy('order')
            ->get()
            ->map(function ($faq) use ($lang) {
                $lang = $lang ?? app()->getLocale();
                return [
                    'id' => $faq->id,
                    'question' => $faq->getTranslation('question', $lang),
                    'answer' => $faq->getTranslation('answer', $lang),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $faqs
        ]);
    }
}
