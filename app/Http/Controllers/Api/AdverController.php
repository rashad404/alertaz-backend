<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Adver;

class AdverController extends Controller
{
    public function index()
    {
        $advers = Adver::all()->map(function ($item) {
            return [
                'id' => $item->id,
                'position' => $item->position,
                'iframe' => $item->iframe,
                'link' => $item->link,
                'image' => $item->image,
            ];
        });
        return response()->json($advers);
    }
} 