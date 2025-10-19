<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomePageAd;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdController extends Controller
{
    protected $imageService;

    public function __construct(ImageUploadService $imageService)
    {
        $this->imageService = $imageService;
    }
    public function index(Request $request)
    {
        \Log::info('Admin AdController index called', ['params' => $request->all()]);
        
        $query = HomePageAd::query();
        
        if ($request->has('place')) {
            $query->where('place', $request->place);
        }
        
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        $ads = $query->orderBy('order', 'asc')
                     ->orderBy('created_at', 'desc')
                     ->paginate($request->per_page ?? 10);

        // Fix image paths to include /storage/ prefix
        $ads->getCollection()->transform(function ($ad) {
            if ($ad->image && !str_starts_with($ad->image, '/storage/') && !str_starts_with($ad->image, 'http')) {
                $ad->image = '/storage/' . $ad->image;
            }
            return $ad;
        });

        \Log::info('Admin AdController returning', ['count' => $ads->count()]);

        return response()->json([
            'success' => true,
            'data' => $ads
        ]);
    }
    
    public function show($id)
    {
        $ad = HomePageAd::findOrFail($id);

        // Fix image path to include /storage/ prefix
        if ($ad->image && !str_starts_with($ad->image, '/storage/') && !str_starts_with($ad->image, 'http')) {
            $ad->image = '/storage/' . $ad->image;
        }

        return response()->json([
            'success' => true,
            'data' => $ad
        ]);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'place' => 'required|string|in:hero_section,home_slider,sidebar,banner,footer,popup',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif,svg|max:5120',
            'iframe' => 'nullable|string',
            'url' => 'nullable|url',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $data = $request->only(['place', 'iframe', 'url', 'order', 'is_active']);

        if ($request->hasFile('image')) {
            $path = $this->imageService->upload($request->file('image'), 'ads');
            $data['image'] = $path;
        }
        
        $ad = HomePageAd::create($data);
        
        return response()->json([
            'success' => true,
            'data' => $ad,
            'message' => 'Ad created successfully'
        ], 201);
    }
    
    public function update(Request $request, $id)
    {
        $ad = HomePageAd::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'place' => 'string|in:hero_section,home_slider,sidebar,banner,footer,popup',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif,svg|max:5120',
            'iframe' => 'nullable|string',
            'url' => 'nullable|url',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $data = $request->only(['place', 'iframe', 'url', 'order', 'is_active']);

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($ad->image) {
                $this->imageService->delete($ad->image);
            }

            $path = $this->imageService->upload($request->file('image'), 'ads');
            $data['image'] = $path;
        }
        
        $ad->update($data);
        
        return response()->json([
            'success' => true,
            'data' => $ad,
            'message' => 'Ad updated successfully'
        ]);
    }
    
    public function destroy($id)
    {
        $ad = HomePageAd::findOrFail($id);

        // Delete image if exists
        if ($ad->image) {
            $this->imageService->delete($ad->image);
        }

        $ad->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Ad deleted successfully'
        ]);
    }
    
    public function uploadImage(Request $request, $id)
    {
        $ad = HomePageAd::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpg,jpeg,png,gif,svg|max:5120'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Delete old image if exists
        if ($ad->image) {
            $this->imageService->delete($ad->image);
        }

        $path = $this->imageService->upload($request->file('image'), 'ads');

        $ad->update(['image' => $path]);

        return response()->json([
            'success' => true,
            'data' => ['image' => $path],
            'message' => 'Image uploaded successfully'
        ]);
    }
    
    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ads' => 'required|array',
            'ads.*.id' => 'required|exists:home_page_ads,id',
            'ads.*.order' => 'required|integer|min:0'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        foreach ($request->ads as $adData) {
            HomePageAd::where('id', $adData['id'])
                      ->update(['order' => $adData['order']]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Ads reordered successfully'
        ]);
    }
    
    public function toggleStatus($id)
    {
        $ad = HomePageAd::findOrFail($id);
        $ad->is_active = !$ad->is_active;
        $ad->save();
        
        return response()->json([
            'success' => true,
            'data' => $ad,
            'message' => 'Ad status updated successfully'
        ]);
    }
}