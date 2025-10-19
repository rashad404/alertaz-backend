<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyType;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CompanyTypeController extends Controller
{
    protected $imageService;

    public function __construct(ImageUploadService $imageService)
    {
        $this->imageService = $imageService;
    }
    public function index()
    {
        // Get parent types with their children
        $types = CompanyType::with('children')
            ->whereNull('parent_id')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
        
        // Ensure translatable fields are properly decoded for parents and children
        $types = $types->map(function ($type) {
            // Decode parent type fields
            if (is_string($type->type_name)) {
                $type->title = json_decode($type->type_name, true) ?: $type->type_name;
            }
            if (is_string($type->description)) {
                $type->description = json_decode($type->description, true) ?: $type->description;
            }
            
            // Process children
            if ($type->children) {
                $type->children = $type->children->map(function ($child) {
                    if (is_string($child->type_name)) {
                        $child->title = json_decode($child->type_name, true) ?: $child->type_name;
                    }
                    if (is_string($child->description)) {
                        $child->description = json_decode($child->description, true) ?: $child->description;
                    }
                    return $child;
                });
            }
            
            return $type;
        });
        
        return response()->json($types);
    }

    public function show($id)
    {
        $type = CompanyType::findOrFail($id);
        
        // Ensure translatable fields are properly decoded
        if (is_string($type->title)) {
            $type->title = json_decode($type->title, true) ?: $type->title;
        }
        if (is_string($type->icon_alt_text)) {
            $type->icon_alt_text = json_decode($type->icon_alt_text, true) ?: $type->icon_alt_text;
        }
        if (is_string($type->seo_title)) {
            $type->seo_title = json_decode($type->seo_title, true) ?: $type->seo_title;
        }
        if (is_string($type->seo_keywords)) {
            $type->seo_keywords = json_decode($type->seo_keywords, true) ?: $type->seo_keywords;
        }
        if (is_string($type->seo_description)) {
            $type->seo_description = json_decode($type->seo_description, true) ?: $type->seo_description;
        }
        
        return response()->json($type);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|array',
            'title.az' => 'required|string',
            'title.en' => 'required|string',
            'title.ru' => 'required|string',
            'slug' => 'required|string|unique:company_types',
            'icon' => 'nullable|image|max:2048',
            'icon_alt_text' => 'nullable|array',
            'seo_title' => 'nullable|array',
            'seo_keywords' => 'nullable|array',
            'seo_description' => 'nullable|array',
            'order' => 'nullable|integer',
            'status' => 'required|boolean',
        ]);

        $data = $request->except(['icon']);

        // Handle icon upload
        if ($request->hasFile('icon')) {
            $path = $this->imageService->upload($request->file('icon'), 'company-types');
            $data['icon'] = $path;
        }

        $type = CompanyType::create($data);
        
        return response()->json($type, 201);
    }

    public function update(Request $request, $id)
    {
        $type = CompanyType::findOrFail($id);
        
        $validated = $request->validate([
            'title' => 'required|array',
            'title.az' => 'required|string',
            'title.en' => 'required|string',
            'title.ru' => 'required|string',
            'slug' => 'required|string|unique:company_types,slug,' . $id,
            'icon' => 'nullable|image|max:2048',
            'icon_alt_text' => 'nullable|array',
            'seo_title' => 'nullable|array',
            'seo_keywords' => 'nullable|array',
            'seo_description' => 'nullable|array',
            'order' => 'nullable|integer',
            'status' => 'required|boolean',
        ]);

        $data = $request->except(['icon', '_method']);

        // Handle icon upload
        if ($request->hasFile('icon')) {
            // Delete old icon if exists
            if ($type->icon) {
                $this->imageService->delete($type->icon);
            }

            $path = $this->imageService->upload($request->file('icon'), 'company-types');
            $data['icon'] = $path;
        }

        $type->update($data);
        
        return response()->json($type);
    }

    public function destroy($id)
    {
        $type = CompanyType::findOrFail($id);
        
        // Check if type has companies
        if ($type->companies()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete company type with associated companies'
            ], 422);
        }
        
        // Delete icon if exists
        if ($type->icon && file_exists(public_path($type->icon))) {
            unlink(public_path($type->icon));
        }
        
        $type->delete();
        
        return response()->json(['message' => 'Company type deleted successfully']);
    }
}