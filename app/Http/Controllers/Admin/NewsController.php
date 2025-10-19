<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Services\ImageUploadService;
use App\Http\Resources\Admin\NewsResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsController extends Controller
{
    protected $imageService;

    public function __construct(ImageUploadService $imageService)
    {
        $this->imageService = $imageService;
    }
    
    public function test()
    {
        $paginated = News::paginate(10);
        return response()->json([
            'news_count' => News::count(),
            'paginated_total' => $paginated->total(),
            'paginated_count' => $paginated->count(),
            'paginated_data' => $paginated,
        ]);
    }

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $news = News::with(['category', 'company'])->paginate($perPage);
        return NewsResource::collection($news);
    }
    
    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $query = News::with(['category', 'company']);
        
        // Apply filters
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }
        
        if ($request->has('language')) {
            $query->where('language', $request->get('language'));
        }
        
        if ($request->has('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }
        
        if ($request->has('status')) {
            $status = $request->get('status');
            // Convert string boolean to actual boolean for database
            if ($status === 'true' || $status === '1' || $status === 1 || $status === true) {
                $query->where('status', true);
            } elseif ($status === 'false' || $status === '0' || $status === 0 || $status === false) {
                $query->where('status', false);
            }
        }

        if ($request->has('author')) {
            $author = $request->get('author');
            $query->where('author', 'like', "%{$author}%");
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        
        return NewsResource::collection($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'language' => 'required|in:az,en,ru',
            'title' => 'required|string|max:255',
            'sub_title' => 'nullable|string|max:255',
            'body' => 'required|string',
            'category_id' => 'required|exists:news_categories,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:news_categories,id',
            'company_id' => 'nullable|exists:companies,id',
            'news_type' => 'nullable|in:private,official,press,interview,analysis,translation,other',
            'status' => 'boolean',
            'show_in_slider' => 'boolean',
            'slider_order' => 'nullable|integer|min:0',
            'publish_date' => 'nullable|date',
            'author' => 'nullable|string|max:255',
            'author_id' => 'nullable|exists:users,id',
            'hashtags' => 'nullable|array',
            'seo_title' => 'nullable|string|max:255',
            'seo_keywords' => 'nullable|string',
            'seo_description' => 'nullable|string',
        ]);

        // Generate slug
        $validated['slug'] = Str::slug($validated['title']);
        
        // Ensure unique slug
        $count = 1;
        $originalSlug = $validated['slug'];
        while (News::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $count;
            $count++;
        }

        // Set default publish date if not provided
        if (!isset($validated['publish_date'])) {
            $validated['publish_date'] = now();
        }

        // Extract category_ids before creating news
        $categoryIds = $validated['category_ids'] ?? [];
        unset($validated['category_ids']);

        $news = News::create($validated);
        
        // Attach multiple categories if provided
        if (!empty($categoryIds)) {
            $attachData = [];
            foreach ($categoryIds as $index => $categoryId) {
                // First category is primary
                $attachData[$categoryId] = ['is_primary' => $index === 0];
            }
            $news->categories()->attach($attachData);
        }
        
        $news->load(['category', 'categories', 'company']);

        return response()->json([
            'message' => 'News created successfully',
            'data' => new NewsResource($news)
        ], 201);
    }

    public function show($id)
    {
        $news = News::with(['category', 'categories', 'company'])->findOrFail($id);
        return new NewsResource($news);
    }

    public function update(Request $request, $id)
    {
        $news = News::findOrFail($id);

        $validated = $request->validate([
            'language' => 'sometimes|in:az,en,ru',
            'title' => 'sometimes|string|max:255',
            'sub_title' => 'nullable|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'body' => 'sometimes|string',
            'category_id' => 'sometimes|exists:news_categories,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:news_categories,id',
            'company_id' => 'nullable|exists:companies,id',
            'news_type' => 'nullable|in:private,official,press,interview,analysis,translation,other',
            'status' => 'boolean',
            'show_in_slider' => 'boolean',
            'slider_order' => 'nullable|integer|min:0',
            'publish_date' => 'nullable|date',
            'author' => 'nullable|string|max:255',
            'author_id' => 'nullable|exists:users,id',
            'hashtags' => 'nullable|array',
            'seo_title' => 'nullable|string|max:255',
            'seo_keywords' => 'nullable|string',
            'seo_description' => 'nullable|string',
        ]);

        // Validate slug uniqueness if provided and not empty
        if (isset($validated['slug']) && !empty($validated['slug'])) {
            // Ensure unique slug (excluding current news item)
            $slugExists = News::where('slug', $validated['slug'])
                ->where('id', '!=', $id)
                ->exists();

            if ($slugExists) {
                return response()->json([
                    'message' => 'Slug already exists',
                    'errors' => ['slug' => ['This slug is already in use']]
                ], 422);
            }
        } else if (isset($validated['slug']) && empty($validated['slug'])) {
            // If slug is empty, remove it from validated data to keep existing slug
            unset($validated['slug']);
        }

        // Handle category_ids separately
        if (isset($validated['category_ids'])) {
            $categoryIds = $validated['category_ids'];
            unset($validated['category_ids']);
            
            // Sync categories with pivot data
            $syncData = [];
            foreach ($categoryIds as $index => $categoryId) {
                // First category is primary
                $syncData[$categoryId] = ['is_primary' => $index === 0];
            }
            $news->categories()->sync($syncData);
        }

        $news->update($validated);
        $news->load(['category', 'categories', 'company']);

        return response()->json([
            'message' => 'News updated successfully',
            'data' => new NewsResource($news)
        ]);
    }

    public function destroy($id)
    {
        $news = News::findOrFail($id);

        // Delete associated image
        if ($news->thumbnail_image) {
            $this->imageService->delete($news->thumbnail_image);
        }

        $news->delete();

        return response()->json([
            'message' => 'News deleted successfully'
        ]);
    }

    public function uploadImage(Request $request, $id)
    {
        $news = News::findOrFail($id);

        $request->validate([
            'image' => 'required|image|max:10240' // Max 10MB
        ]);

        // Delete old image if exists
        if ($news->thumbnail_image) {
            $this->imageService->delete($news->thumbnail_image);
        }

        // Upload new image
        $path = $this->imageService->upload($request->file('image'), 'news');

        $news->update(['thumbnail_image' => $path]);

        return response()->json([
            'message' => 'Image uploaded successfully',
            'path' => $path,
            'url' => asset('storage/' . $path)
        ]);
    }
    
    public function uploadContentImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240' // Max 10MB
        ]);

        // Upload image for content
        $path = $this->imageService->upload($request->file('image'), 'content');

        return response()->json([
            'success' => true,
            'url' => asset('storage/' . $path)
        ]);
    }
}