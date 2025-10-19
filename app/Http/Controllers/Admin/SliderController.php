<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeSliderNews;
use App\Models\News;
use Illuminate\Http\Request;

class SliderController extends Controller
{
    public function index()
    {
        $sliders = HomeSliderNews::with(['news' => function($query) {
            $query->select('id', 'title', 'language', 'status', 'publish_date', 'thumbnail_image');
        }])
        ->orderBy('order')
        ->get()
        ->map(function ($slider) {
            return [
                'id' => $slider->id,
                'news_id' => $slider->news_id,
                'order' => $slider->order,
                'news' => $slider->news ? [
                    'id' => $slider->news->id,
                    'title' => $slider->news->title,
                    'language' => $slider->news->language,
                    'status' => $slider->news->status,
                    'publish_date' => $slider->news->publish_date,
                    'thumbnail_image' => $slider->news->thumbnail_image,
                ] : null,
                'created_at' => $slider->created_at,
                'updated_at' => $slider->updated_at,
            ];
        });

        return response()->json($sliders);
    }

    public function availableNews(Request $request)
    {
        $language = $request->get('language', 'az');
        $search = $request->get('search', '');
        
        // Get already selected news IDs
        $selectedIds = HomeSliderNews::pluck('news_id')->toArray();
        
        $query = News::where('language', $language)
            ->where('status', true)
            ->whereNotIn('id', $selectedIds);
            
        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }
        
        $news = $query->orderBy('publish_date', 'desc')
            ->limit(50)
            ->get(['id', 'title', 'publish_date', 'thumbnail_image']);
            
        return response()->json($news);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'news_id' => 'required|exists:news,id|unique:home_slider_news,news_id',
            'order' => 'nullable|integer|min:0',
        ]);

        $slider = HomeSliderNews::create($validated);
        $slider->load('news');

        return response()->json([
            'message' => 'Slider item created successfully',
            'data' => $slider
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $slider = HomeSliderNews::findOrFail($id);
        
        $validated = $request->validate([
            'order' => 'required|integer|min:0',
        ]);

        $slider->update($validated);

        return response()->json([
            'message' => 'Slider order updated successfully',
            'data' => $slider
        ]);
    }

    public function destroy($id)
    {
        $slider = HomeSliderNews::findOrFail($id);
        $slider->delete();

        return response()->json([
            'message' => 'Slider item removed successfully'
        ]);
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:home_slider_news,id',
            'items.*.order' => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            HomeSliderNews::where('id', $item['id'])
                ->update(['order' => $item['order']]);
        }

        return response()->json([
            'message' => 'Slider order updated successfully'
        ]);
    }
}