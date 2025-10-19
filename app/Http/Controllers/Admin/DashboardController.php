<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\User;
use App\Models\Offer;
use App\Models\Blog;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $recentNews = News::with('category')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
            
        // Transform category titles using getTranslation
        $recentNews->transform(function($news) {
            if ($news->category) {
                $news->category->title = $news->category->getTranslation('title', $news->language);
            }
            return $news;
        });
        
        $stats = [
            'total_news' => News::count(),
            'active_news' => News::where('status', true)->count(),
            'total_users' => User::count(),
            'total_offers' => Offer::count(),
            'total_blogs' => Blog::count(),
            'recent_news' => $recentNews,
        ];

        return response()->json($stats);
    }
}