<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\News;

class HomePageNewsSectionController extends Controller
{
    public function index($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        $newsQuery = News::orderByDesc('publish_date');
        $paginated = $newsQuery->paginate(9);
        $paginated->getCollection()->transform(function ($news) use ($lang) {
            return [
                'id' => $news->id,
                'title' => $news->getTranslation('title', $lang),
                'views' => $news->views,
                'author' => $news->author,
                'hashtags' => $news->hashtags,
                'body' => $news->getTranslation('body', $lang),
                'category_id' => $news->category_id,
                'thumbnail_image' => $news->thumbnail_image,
                'publish_date' => $news->publish_date,
                'seo_title' => $news->seo_title,
                'seo_keywords' => $news->seo_keywords,
                'seo_description' => $news->seo_description,
                'created_at' => $news->created_at,
                'updated_at' => $news->updated_at,
            ];
        });
        return response()->json($paginated);
    }
} 