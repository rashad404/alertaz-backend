<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroBanner;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HeroBannerController extends Controller
{
    protected $imageService;

    public function __construct(ImageUploadService $imageService)
    {
        $this->imageService = $imageService;
    }
    public function index(Request $request)
    {
        $query = HeroBanner::query();
        
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        $banners = $query->orderBy('order', 'asc')
                        ->orderBy('created_at', 'desc')
                        ->paginate($request->per_page ?? 10);
        
        return response()->json([
            'success' => true,
            'data' => $banners
        ]);
    }
    
    public function show($id)
    {
        $banner = HeroBanner::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $banner
        ]);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|array',
            'title.az' => 'required|string',
            'description' => 'nullable|array',
            'description.az' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif,svg|max:5120',
            'link' => 'nullable|string',
            'link_text' => 'nullable|array',
            'link_text.az' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $data = $request->only(['title', 'description', 'link', 'link_text', 'order', 'is_active']);

        if ($request->hasFile('image')) {
            $path = $this->imageService->upload($request->file('image'), 'hero-banners');
            $data['image'] = $path;
        }
        
        $banner = HeroBanner::create($data);
        
        return response()->json([
            'success' => true,
            'data' => $banner,
            'message' => 'Hero banner created successfully'
        ], 201);
    }
    
    public function update(Request $request, $id)
    {
        $banner = HeroBanner::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'array',
            'title.az' => 'string',
            'description' => 'nullable|array',
            'description.az' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif,svg|max:5120',
            'link' => 'nullable|string',
            'link_text' => 'nullable|array',
            'link_text.az' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $data = $request->only(['title', 'description', 'link', 'link_text', 'order', 'is_active']);

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($banner->image) {
                $this->imageService->delete($banner->image);
            }

            $path = $this->imageService->upload($request->file('image'), 'hero-banners');
            $data['image'] = $path;
        }
        
        $banner->update($data);
        
        return response()->json([
            'success' => true,
            'data' => $banner,
            'message' => 'Hero banner updated successfully'
        ]);
    }
    
    public function destroy($id)
    {
        $banner = HeroBanner::findOrFail($id);

        // Delete image if exists
        if ($banner->image) {
            $this->imageService->delete($banner->image);
        }

        $banner->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Hero banner deleted successfully'
        ]);
    }
    
    public function uploadImage(Request $request, $id)
    {
        $banner = HeroBanner::findOrFail($id);
        
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
        if ($banner->image) {
            $this->imageService->delete($banner->image);
        }

        $path = $this->imageService->upload($request->file('image'), 'hero-banners');

        $banner->update(['image' => $path]);

        return response()->json([
            'success' => true,
            'data' => ['image' => $path],
            'message' => 'Image uploaded successfully'
        ]);
    }
    
    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'banners' => 'required|array',
            'banners.*.id' => 'required|exists:hero_banners,id',
            'banners.*.order' => 'required|integer|min:0'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        foreach ($request->banners as $bannerData) {
            HeroBanner::where('id', $bannerData['id'])
                      ->update(['order' => $bannerData['order']]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Hero banners reordered successfully'
        ]);
    }
    
    public function toggleStatus($id)
    {
        $banner = HeroBanner::findOrFail($id);
        $banner->is_active = !$banner->is_active;
        $banner->save();
        
        return response()->json([
            'success' => true,
            'data' => $banner,
            'message' => 'Hero banner status updated successfully'
        ]);
    }
}