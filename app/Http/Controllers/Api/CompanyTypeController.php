<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyType;
use Illuminate\Support\Facades\DB;

class CompanyTypeController extends Controller
{
    /**
     * Get hierarchical company types for menu
     */
    public function getHierarchicalTypes($lang = 'az')
    {
        app()->setLocale($lang);
        
        // Get parent types with their children
        $types = CompanyType::whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('display_order');
            }])
            ->orderBy('display_order')
            ->get()
            ->map(function ($type) use ($lang) {
                $typeName = json_decode($type->type_name, true);
                $typeDesc = json_decode($type->description, true);
                
                return [
                    'id' => $type->id,
                    'name' => $typeName[$lang] ?? $typeName['en'] ?? $type->type_name,
                    'slug' => $type->slug,
                    'description' => $typeDesc[$lang] ?? $typeDesc['en'] ?? null,
                    'children' => $type->children->map(function ($child) use ($lang) {
                        $childName = json_decode($child->type_name, true);
                        $childDesc = json_decode($child->description, true);
                        
                        return [
                            'id' => $child->id,
                            'name' => $childName[$lang] ?? $childName['en'] ?? $child->type_name,
                            'slug' => $child->slug,
                            'description' => $childDesc[$lang] ?? $childDesc['en'] ?? null,
                        ];
                    })
                ];
            });
        
        return response()->json([
            'status' => 'success',
            'data' => $types
        ]);
    }
 

    public function homePageCompanies($lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        // Define the company types we want to show on homepage by slug (using Azerbaijani slugs)
        $targetSlugs = ['banklar', 'sigorta', 'kredit-teskilatlari'];
        
        $types = [];
        
        foreach ($targetSlugs as $slug) {
            // Get the company type by slug
            $companyType = CompanyType::where('slug', $slug)
                ->whereNull('parent_id')
                ->first();
                
            if (!$companyType) continue;
            
            // Decode the JSON type_name field
            $typeName = is_string($companyType->type_name) 
                ? json_decode($companyType->type_name, true) 
                : $companyType->type_name;
            
            // Get companies for this type directly from companies table
            $companies = DB::table('companies')
                ->where('company_type_id', $companyType->id)
                ->where('is_active', true)
                ->select('id', 'name', 'slug')
                ->orderBy('display_order')
                ->orderBy('id')
                ->limit(3)
                ->get()
                ->map(function ($company) {
                    // Parse company name if it's JSON
                    $name = $company->name;
                    if (is_string($name)) {
                        $decoded = json_decode($name, true);
                        if ($decoded && is_array($decoded)) {
                            $name = $decoded;
                        }
                    }
                    
                    return [
                        'id' => $company->id,
                        'name' => is_array($name) ? $name : [
                            'az' => $name,
                            'en' => $name,
                            'ru' => $name
                        ],
                        'slug' => $company->slug
                    ];
                });
            
            $types[] = [
                'id' => $companyType->id,
                'title' => $typeName,
                'slug' => $companyType->slug,
                'icon' => null,
                'icon_alt_text' => null,
                'companies' => $companies
            ];
        }
        
        return response()->json(['data' => $types]);
    }

    public function getCompaniesByTypeSlug($lang = null, $typeSlug = null)
    {
        // Handle parameter ordering
        if ($typeSlug === null && $lang !== null) {
            $typeSlug = $lang;
            $lang = null;
        }
        
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        // Find the company type by slug
        $companyType = CompanyType::where('slug', $typeSlug)->first();
        if (!$companyType) {
            return response()->json(['data' => [], 'message' => 'Company type not found'], 404);
        }
        
        // Get all companies for this type (including subcategories if parent)
        $query = \App\Models\Company::where('is_active', true);
        
        if ($companyType->isParent()) {
            // Get companies from this type and all its children
            $childIds = $companyType->children()->pluck('id')->toArray();
            $allTypeIds = array_merge([$companyType->id], $childIds);
            $query->whereIn('company_type_id', $allTypeIds);
        } else {
            $query->where('company_type_id', $companyType->id);
        }
        
        $companies = $query->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(function ($company) use ($lang) {
                // Parse company name if it's JSON
                $name = $company->name;
                if (is_string($name)) {
                    $decoded = json_decode($name, true);
                    if ($decoded && is_array($decoded)) {
                        $name = $decoded;
                    }
                }
                
                return [
                    'id' => $company->id,
                    'name' => $name,
                    'slug' => $company->slug,
                    'site' => $company->site ?? $company->website,
                    'phones' => is_string($company->phones) ? json_decode($company->phones, true) : $company->phones,
                    'addresses' => is_string($company->addresses) ? json_decode($company->addresses, true) : $company->addresses,
                    'email' => $company->email,
                ];
            });
            
        return response()->json(['data' => $companies]);
    }
    
    public function companiesByType($lang = null, $type = null)
    {
        // If only one parameter is passed, it's the type (for backward compatibility)
        if ($type === null && $lang !== null) {
            $type = $lang;
            $lang = null;
        }
        
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        // Find the company type by slug first
        $companyType = \App\Models\CompanyType::where('slug', $type)->first();
        if (!$companyType) {
            return response()->json(['data' => [], 'message' => 'Company type not found'], 404);
        }
        
        $companies = \App\Models\Company::where('company_type_id', $companyType->id)
            ->where('status', true)
            ->orderBy('order')
            ->paginate(50);
            
        $companies->getCollection()->transform(function ($company) use ($lang) {
            $name = is_string($company->name) ? (json_decode($company->name, true) ?? $company->name) : $company->name;
            $phones = is_string($company->phones) ? (json_decode($company->phones, true) ?? $company->phones) : $company->phones;
            $addresses = is_string($company->addresses) ? (json_decode($company->addresses, true) ?? $company->addresses) : $company->addresses;
            
            return [
                'id' => $company->id,
                'name' => $name,
                'slug' => $company->slug,
                'logo' => $company->logo ? asset('storage/' . $company->logo) : null,
                'site' => $company->site,
                'phones' => $phones,
                'addresses' => $addresses,
                'email' => $company->email,
            ];
        });
        return response()->json($companies);
    }
} 