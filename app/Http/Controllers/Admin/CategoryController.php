<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\AdminTranslatable;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use AdminTranslatable;
    public function index()
    {
        $categories = Category::orderBy('order')->get();
        
        // Transform category titles for admin panel
        $categories = $categories->map(function($category) {
            // Handle the double-encoded JSON issue
            $rawTitle = $category->getRawOriginal('title');
            
            // Parse the outer JSON
            $outerJson = json_decode($rawTitle, true);
            
            // Check if we have the expected structure
            if (is_array($outerJson) && isset($outerJson['en'])) {
                // The value is a JSON string, decode it
                $innerJson = json_decode($outerJson['en'], true);
                if (is_array($innerJson)) {
                    // We have the actual translations
                    $titleTranslations = $innerJson;
                } else {
                    // Fallback to the original string
                    $titleTranslations = $outerJson['en'];
                }
            } else {
                // Fallback to original
                $titleTranslations = $category->title;
            }
            
            return [
                'id' => $category->id,
                'title' => $titleTranslations, // Return the properly parsed translations
                'slug' => $category->slug,
                'order' => $category->order,
                'status' => $category->status,
            ];
        });
        
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required',
            'slug' => 'nullable|string',
            'order' => 'nullable|integer',
            'status' => 'nullable|boolean',
            'seo_title' => 'nullable',
            'seo_keywords' => 'nullable',
            'seo_description' => 'nullable',
        ]);

        $category = Category::create($validated);

        return response()->json($category, 201);
    }

    public function show($id)
    {
        $category = Category::findOrFail($id);
        
        // Handle the double-encoded JSON issue for single category
        $rawTitle = $category->getRawOriginal('title');
        
        // Parse the outer JSON
        $outerJson = json_decode($rawTitle, true);
        
        // Check if we have the expected structure
        if (is_array($outerJson) && isset($outerJson['en'])) {
            // The value is a JSON string, decode it
            $innerJson = json_decode($outerJson['en'], true);
            if (is_array($innerJson)) {
                // We have the actual translations
                $titleTranslations = $innerJson;
            } else {
                // Fallback to the original string
                $titleTranslations = $outerJson['en'];
            }
        } else {
            // Fallback to original
            $titleTranslations = $category->title;
        }
        
        return response()->json([
            'id' => $category->id,
            'title' => $titleTranslations,
            'slug' => $category->slug,
            'order' => $category->order,
            'status' => $category->status,
            'seo_title' => $category->seo_title,
            'seo_keywords' => $category->seo_keywords,
            'seo_description' => $category->seo_description,
        ]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        
        $validated = $request->validate([
            'title' => 'sometimes|required',
            'slug' => 'nullable|string',
            'order' => 'nullable|integer',
            'status' => 'nullable|boolean',
            'seo_title' => 'nullable',
            'seo_keywords' => 'nullable',
            'seo_description' => 'nullable',
        ]);

        $category->update($validated);

        return response()->json($category);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();
        
        return response()->json(['message' => 'Category deleted successfully']);
    }
}