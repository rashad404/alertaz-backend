<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;

class PartnerController extends Controller
{
    public function index($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        
        $partners = Partner::where('status', true)
            ->orderBy('order')
            ->get()
            ->map(function ($partner) {
                return [
                    'id' => $partner->id,
                    'title' => $partner->title,
                    'image' => $partner->image ? asset('storage/' . $partner->image) : null,
                    'order' => $partner->order,
                ];
            });
            
        return response()->json($partners);
    }
}
