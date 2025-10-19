<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImageSection;

class ImageSectionController extends Controller
{
    public function index()
    {
        $images = ImageSection::all()->map(function ($item) {
            return [
                'id' => $item->id,
                'logo' => $item->logo,
                'banner_image' => $item->banner_image,
            ];
        });
        return response()->json($images);
    }
} 