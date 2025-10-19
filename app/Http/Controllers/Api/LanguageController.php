<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Language;

class LanguageController extends Controller
{
    public function index()
    {
        $languages = Language::all()->map(function ($lang) {
            return [
                'id' => $lang->id,
                'lang_code' => $lang->lang_code,
                'title' => $lang->title,
            ];
        });
        return response()->json($languages);
    }
}
