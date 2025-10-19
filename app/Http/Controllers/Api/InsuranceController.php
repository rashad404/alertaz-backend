<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Insurance;
use App\Models\InsuranceCategory;
use App\Models\InsuranceProvider;
use Illuminate\Http\Request;

class InsuranceController extends Controller
{
    /**
     * Transform insurance data to return translated values
     */
    private function transformInsurance($insurance, $lang)
    {
        return [
            'id' => $insurance->id,
            'slug' => $insurance->slug,
            'title' => $insurance->getTranslation('title', $lang),
            'description' => $insurance->getTranslation('description', $lang),
            'coverage_amount' => $insurance->coverage_amount,
            'premium' => $insurance->premium,
            'duration' => $insurance->getTranslation('duration', $lang),
            'features' => $insurance->getTranslation('features', $lang),
            'requirements' => $insurance->getTranslation('requirements', $lang),
            'documents' => $insurance->getTranslation('documents', $lang),
            'exclusions' => $insurance->getTranslation('exclusions', $lang),
            'image' => $insurance->image,
            'is_featured' => $insurance->is_featured,
            'views' => $insurance->views,
            'category' => $insurance->category ? [
                'id' => $insurance->category->id,
                'title' => $insurance->category->getTranslation('title', $lang),
                'slug' => $insurance->category->slug,
                'icon' => $insurance->category->icon
            ] : null,
            'provider' => $insurance->provider ? [
                'id' => $insurance->provider->id,
                'name' => $insurance->provider->getTranslation('name', $lang),
                'slug' => $insurance->provider->slug,
                'logo' => $insurance->provider->logo
            ] : null,
            'advantages' => $insurance->advantages ? $insurance->advantages->map(function($advantage) use ($lang) {
                return [
                    'id' => $advantage->id,
                    'title' => $advantage->getTranslation('title', $lang),
                    'description' => $advantage->getTranslation('description', $lang),
                    'icon' => $advantage->icon
                ];
            }) : []
        ];
    }
    
    /**
     * Get all insurance categories
     */
    public function categories(Request $request, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        $categories = InsuranceCategory::where('status', true)
            ->withCount('insurances')
            ->orderBy('order')
            ->get()
            ->map(function($category) use ($lang) {
                return [
                    'id' => $category->id,
                    'title' => $category->getTranslation('title', $lang),
                    'slug' => $category->slug,
                    'icon' => $category->icon,
                    'order' => $category->order,
                    'insurances_count' => $category->insurances_count
                ];
            });
            
        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
    
    /**
     * Get all insurance products with filters
     */
    public function index(Request $request, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        $query = Insurance::with(['category', 'provider', 'advantages'])
            ->where('status', true);
        
        // Filter by category
        if ($request->has('category')) {
            $category = InsuranceCategory::where('slug', $request->category)->first();
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }
        
        // Filter by provider
        if ($request->has('provider')) {
            $query->where('provider_id', $request->provider);
        }
        
        // Filter by featured
        if ($request->has('featured') && $request->featured) {
            $query->where('is_featured', true);
        }
        
        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search, $lang) {
                $q->whereRaw("JSON_EXTRACT(title, '$.$lang') LIKE ?", ["%$search%"])
                  ->orWhereRaw("JSON_EXTRACT(description, '$.$lang') LIKE ?", ["%$search%"]);
            });
        }
        
        // Sorting
        $sortBy = $request->get('sort', 'order');
        $sortOrder = $request->get('order', 'asc');
        
        if ($sortBy === 'popular') {
            $query->orderBy('views', 'desc');
        } elseif ($sortBy === 'newest') {
            $query->orderBy('created_at', 'desc');
        } else {
            $query->orderBy('order', 'asc');
        }
        
        $perPage = $request->get('per_page', 12);
        $insurances = $query->paginate($perPage);
        
        // Transform the data to return translated values
        $insurances->getCollection()->transform(function($insurance) use ($lang) {
            return $this->transformInsurance($insurance, $lang);
        });
        
        return response()->json($insurances);
    }
    
    /**
     * Get single insurance product by ID or slug
     */
    public function show(Request $request, $lang = null, $identifier)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        // Check if identifier is numeric (ID) or string (slug)
        if (is_numeric($identifier)) {
            $insurance = Insurance::with(['category', 'provider', 'advantages'])
                ->where('status', true)
                ->findOrFail($identifier);
        } else {
            $insurance = Insurance::with(['category', 'provider', 'advantages'])
                ->where('status', true)
                ->where('slug', $identifier)
                ->firstOrFail();
        }
        
        // Increment views
        $insurance->increment('views');
        
        return response()->json([
            'success' => true,
            'data' => $this->transformInsurance($insurance, $lang)
        ]);
    }
    
    /**
     * Get featured insurance products
     */
    public function featured(Request $request, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        $insurances = Insurance::with(['category', 'provider', 'advantages'])
            ->where('status', true)
            ->where('is_featured', true)
            ->orderBy('order')
            ->limit(6)
            ->get()
            ->map(function($insurance) use ($lang) {
                return $this->transformInsurance($insurance, $lang);
            });
        
        return response()->json([
            'success' => true,
            'data' => $insurances
        ]);
    }
    
    /**
     * Get similar insurance products
     */
    public function similar(Request $request, $lang = null, $id)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        $insurance = Insurance::findOrFail($id);
        
        $similar = Insurance::with(['category', 'provider', 'advantages'])
            ->where('status', true)
            ->where('category_id', $insurance->category_id)
            ->where('id', '!=', $id)
            ->orderBy('views', 'desc')
            ->limit(4)
            ->get()
            ->map(function($insurance) use ($lang) {
                return $this->transformInsurance($insurance, $lang);
            });
        
        return response()->json([
            'success' => true,
            'data' => $similar
        ]);
    }
    
    /**
     * Compare multiple insurance products
     */
    public function compare(Request $request, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        $request->validate([
            'ids' => 'required|array|min:2|max:4',
            'ids.*' => 'exists:insurances,id'
        ]);
        
        $insurances = Insurance::with(['category', 'provider', 'advantages'])
            ->whereIn('id', $request->ids)
            ->where('status', true)
            ->get()
            ->map(function($insurance) use ($lang) {
                return $this->transformInsurance($insurance, $lang);
            });
        
        return response()->json([
            'success' => true,
            'data' => $insurances
        ]);
    }
    
    /**
     * Get all insurance providers
     */
    public function providers(Request $request, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        $providers = InsuranceProvider::where('status', true)
            ->withCount('insurances')
            ->get()
            ->map(function($provider) use ($lang) {
                return [
                    'id' => $provider->id,
                    'name' => $provider->getTranslation('name', $lang),
                    'slug' => $provider->slug,
                    'logo' => $provider->logo,
                    'description' => $provider->getTranslation('description', $lang),
                    'website' => $provider->website,
                    'insurances_count' => $provider->insurances_count
                ];
            })
            ->sortBy('name')
            ->values();
        
        return response()->json([
            'success' => true,
            'data' => $providers
        ]);
    }
}