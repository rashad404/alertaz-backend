<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\ImageUploadService;
use App\Traits\AdminTranslatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    use AdminTranslatable;

    protected $imageService;

    public function __construct(ImageUploadService $imageService)
    {
        $this->imageService = $imageService;
    }
    
    public function index()
    {
        $companies = Company::with('type')->orderBy('created_at', 'desc')->get();
        
        // Ensure translatable fields are properly decoded
        $companies = $companies->map(function ($company) {
            // Decode JSON fields if they're strings
            if (is_string($company->name)) {
                $company->name = json_decode($company->name, true) ?: $company->name;
            }
            if (is_string($company->short_name)) {
                $company->short_name = json_decode($company->short_name, true) ?: $company->short_name;
            }
            if (is_string($company->about)) {
                $company->about = json_decode($company->about, true) ?: $company->about;
            }
            if (is_string($company->seo_title)) {
                $company->seo_title = json_decode($company->seo_title, true) ?: $company->seo_title;
            }
            if (is_string($company->seo_keywords)) {
                $company->seo_keywords = json_decode($company->seo_keywords, true) ?: $company->seo_keywords;
            }
            if (is_string($company->seo_description)) {
                $company->seo_description = json_decode($company->seo_description, true) ?: $company->seo_description;
            }

            // Fix logo path to include /storage/ prefix (frontend will add base URL)
            if ($company->logo && !str_starts_with($company->logo, '/storage/') && !str_starts_with($company->logo, 'http')) {
                $company->logo = '/storage/' . $company->logo;
            }

            return $company;
        });

        return response()->json($companies);
    }

    public function show($id)
    {
        $company = Company::with('type')->findOrFail($id);
        
        // Ensure translatable fields are properly decoded
        if (is_string($company->name)) {
            $company->name = json_decode($company->name, true) ?: $company->name;
        }
        if (is_string($company->short_name)) {
            $company->short_name = json_decode($company->short_name, true) ?: $company->short_name;
        }
        if (is_string($company->about)) {
            $company->about = json_decode($company->about, true) ?: $company->about;
        }
        if (is_string($company->seo_title)) {
            $company->seo_title = json_decode($company->seo_title, true) ?: $company->seo_title;
        }
        if (is_string($company->seo_keywords)) {
            $company->seo_keywords = json_decode($company->seo_keywords, true) ?: $company->seo_keywords;
        }
        if (is_string($company->seo_description)) {
            $company->seo_description = json_decode($company->seo_description, true) ?: $company->seo_description;
        }

        // Fix logo path to include /storage/ prefix (frontend will add base URL)
        if ($company->logo && !str_starts_with($company->logo, '/storage/') && !str_starts_with($company->logo, 'http')) {
            $company->logo = '/storage/' . $company->logo;
        }

        return response()->json($company);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|array',
            'name.az' => 'required|string',
            'name.en' => 'required|string',
            'name.ru' => 'required|string',
            'short_name' => 'nullable|array',
            'slug' => 'required|string|unique:companies',
            'company_type_id' => 'required|exists:company_types,id',
            'logo' => 'nullable|image|max:2048',
            'about' => 'nullable|array',
            'establishment_date' => 'nullable|date',
            'voen' => 'nullable|string',
            'bank_code' => 'nullable|string',
            'correspondent_account' => 'nullable|string',
            'swift_code' => 'nullable|string',
            'reuters_dealing' => 'nullable|string',
            'site' => 'nullable|url',
            'phones' => 'nullable|string',
            'email' => 'nullable|email',
            'addresses' => 'nullable|string',
            'requisites' => 'nullable|string',
            'business_hours' => 'nullable|string',
            'seo_title' => 'nullable|array',
            'seo_keywords' => 'nullable|array',
            'seo_description' => 'nullable|array',
            'status' => 'required|in:0,1,true,false',
        ]);

        $data = $request->except(['logo']);

        // Convert status to boolean
        if (isset($data['status'])) {
            $data['status'] = filter_var($data['status'], FILTER_VALIDATE_BOOLEAN);
        }

        // Convert newline-separated strings to arrays
        if (isset($data['phones']) && is_string($data['phones'])) {
            $data['phones'] = array_filter(array_map('trim', explode("\n", $data['phones'])));
        }
        if (isset($data['addresses']) && is_string($data['addresses'])) {
            $data['addresses'] = array_filter(array_map('trim', explode("\n", $data['addresses'])));
        }
        if (isset($data['requisites']) && is_string($data['requisites'])) {
            $data['requisites'] = array_filter(array_map('trim', explode("\n", $data['requisites'])));
        }
        if (isset($data['business_hours']) && is_string($data['business_hours'])) {
            $data['business_hours'] = array_filter(array_map('trim', explode("\n", $data['business_hours'])));
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $path = $this->imageService->upload($request->file('logo'), 'companies');
            $data['logo'] = $path;
        }

        $company = Company::create($data);

        return response()->json($company, 201);
    }

    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|array',
            'name.az' => 'required|string',
            'name.en' => 'required|string',
            'name.ru' => 'required|string',
            'short_name' => 'nullable|array',
            'slug' => 'required|string|unique:companies,slug,' . $id,
            'company_type_id' => 'required|exists:company_types,id',
            'logo' => 'nullable|image|max:2048',
            'about' => 'nullable|array',
            'establishment_date' => 'nullable|date',
            'voen' => 'nullable|string',
            'bank_code' => 'nullable|string',
            'correspondent_account' => 'nullable|string',
            'swift_code' => 'nullable|string',
            'reuters_dealing' => 'nullable|string',
            'site' => 'nullable|url',
            'phones' => 'nullable|string',
            'email' => 'nullable|email',
            'addresses' => 'nullable|string',
            'requisites' => 'nullable|string',
            'business_hours' => 'nullable|string',
            'seo_title' => 'nullable|array',
            'seo_keywords' => 'nullable|array',
            'seo_description' => 'nullable|array',
            'status' => 'required|in:0,1,true,false',
        ]);

        $data = $request->except(['logo', '_method']);

        // Convert status to boolean
        if (isset($data['status'])) {
            $data['status'] = filter_var($data['status'], FILTER_VALIDATE_BOOLEAN);
        }

        // Convert newline-separated strings to arrays
        if (isset($data['phones']) && is_string($data['phones'])) {
            $data['phones'] = array_filter(array_map('trim', explode("\n", $data['phones'])));
        }
        if (isset($data['addresses']) && is_string($data['addresses'])) {
            $data['addresses'] = array_filter(array_map('trim', explode("\n", $data['addresses'])));
        }
        if (isset($data['requisites']) && is_string($data['requisites'])) {
            $data['requisites'] = array_filter(array_map('trim', explode("\n", $data['requisites'])));
        }
        if (isset($data['business_hours']) && is_string($data['business_hours'])) {
            $data['business_hours'] = array_filter(array_map('trim', explode("\n", $data['business_hours'])));
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($company->logo) {
                $this->imageService->delete($company->logo);
            }

            $path = $this->imageService->upload($request->file('logo'), 'companies');
            $data['logo'] = $path;
        }

        $company->update($data);

        return response()->json($company);
    }

    public function destroy($id)
    {
        $company = Company::findOrFail($id);

        // Delete logo if exists
        if ($company->logo) {
            $this->imageService->delete($company->logo);
        }

        $company->delete();
        
        return response()->json(['message' => 'Company deleted successfully']);
    }
    
    public function list(Request $request)
    {
        $query = Company::select('id', 'name', 'logo');
        
        // If search parameter is provided, filter companies
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('name', 'LIKE', "%\"{$search}%");
            });
        }
        
        // If limit is provided (for initial load), limit results
        $limit = $request->get('limit', null);
        if ($limit) {
            $companies = $query->orderBy('name')->limit($limit)->get();
        } else {
            $companies = $query->orderBy('name')->get();
        }
        
        $companies = $companies->map(function ($company) {
            return [
                'id' => $company->id,
                'name' => $this->getTranslatedValue($company->name) ?? 'Company',
                'logo' => $company->logo
            ];
        });
        
        return response()->json($companies);
    }
}
