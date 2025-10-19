<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecommendedBank;

class RecommendedBankController extends Controller
{
    public function index($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $recommendedBanks = RecommendedBank::with('company')
            ->where('status', true)
            ->orderBy('order')
            ->get();
            
        $lang = $lang ?? app()->getLocale();
        
        $recommendedBanks = $recommendedBanks->map(function ($item) use ($lang) {
            return [
                'id' => $item->id,
                'company' => $item->company ? [
                    'id' => $item->company->id,
                    'name' => $item->company->getTranslation('name', $lang),
                    'logo' => $item->company->logo ? asset('storage/' . $item->company->logo) : null,
                    'site' => $item->company->site,
                ] : null,
            ];
        });
        
        return response()->json($recommendedBanks);
    }
} 