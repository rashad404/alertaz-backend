<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function index(Request $request, $lang = null)
    {
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        $query = Company::with('companyType')->active();
        
        // Filter by type if provided
        if ($request->has('type')) {
            $type = $request->get('type');
            
            // Map Azerbaijani URL segments to English first
            $azToEnMapping = [
                'banklar' => 'banks',
                'sigorta' => 'insurance',
                'kredit-teskilatlari' => 'credit-organizations',
                'investisiya' => 'investment',
                'lizinq' => 'leasing',
                'odenis-sistemleri' => 'payment-systems',
            ];
            
            // Convert AZ to EN if needed
            $type = $azToEnMapping[$type] ?? $type;
            
            // Map URL-friendly type names to database type names
            $typeMapping = [
                'banks' => 'bank',
                'insurance' => 'insurance',
                'credit-organizations' => 'credit_organization',
                'investment' => 'investment',
                'leasing' => 'leasing',
                'payment-systems' => 'payment_system'
            ];
            
            $typeName = $typeMapping[$type] ?? $type;
            
            $companyType = CompanyType::where('type_name', $typeName)->first();
            if ($companyType) {
                $query->where('company_type_id', $companyType->id);
            }
        }
        
        // Filter by type_id if provided (for subcategories)
        if ($request->has('type_id')) {
            $typeId = $request->get('type_id');
            
            // Check if this is a subcategory of credit organizations
            $subcategory = DB::table('company_types')
                ->where('id', $typeId)
                ->where('parent_id', 3) // Credit Organizations
                ->first();
            
            if ($subcategory) {
                // This is a credit organization subcategory
                // Get companies that have entities matching this subcategory
                $subcategorySlug = $subcategory->slug;
                
                // Get the mapping from entity_subcategory_mappings table
                $entityNames = DB::table('entity_subcategory_mappings')
                    ->where('subcategory_slug', $subcategorySlug)
                    ->pluck('entity_name')
                    ->toArray();
                
                if (!empty($entityNames)) {
                    // Get company IDs that have these entities
                    $companyIds = DB::table('company_entities')
                        ->where('is_active', true)
                        ->where(function($q) use ($entityNames) {
                            foreach ($entityNames as $name) {
                                $q->orWhereRaw("JSON_VALID(entity_name) AND (JSON_UNQUOTE(JSON_EXTRACT(entity_name, '$.en')) = ? OR JSON_UNQUOTE(JSON_EXTRACT(entity_name, '$.az')) = ?)", [$name, $name]);
                            }
                        })
                        ->pluck('company_id')
                        ->unique()
                        ->toArray();
                    
                    if (!empty($companyIds)) {
                        $query->whereIn('id', $companyIds);
                    } else {
                        // No companies found with matching entities
                        $query->where('id', -1); // Force no results
                    }
                } else {
                    // No mappings found, fallback to parent type
                    $query->where('company_type_id', 3);
                }
            } else {
                // Regular type filter
                $query->where('company_type_id', $typeId);
            }
        }
        
        $companies = $query->orderBy('display_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        $companies->getCollection()->transform(function ($company) use ($lang) {
            // Get all EAV attributes for this company
            $companyData = $company->toArrayWithAttributes();
            
            // Parse translatable fields
            if (isset($companyData['about']) && is_array($companyData['about'])) {
                $companyData['about'] = $companyData['about'][$lang] ?? $companyData['about']['az'] ?? '';
            }
            
            // Format addresses if they exist
            if (isset($companyData['addresses'])) {
                $addresses = is_string($companyData['addresses']) ? json_decode($companyData['addresses'], true) : $companyData['addresses'];
                $companyData['addresses'] = is_array($addresses) ? $addresses : [$addresses];
            }
            
            // Format phones if they exist
            if (isset($companyData['phone'])) {
                $companyData['phones'] = [$companyData['phone']];
                unset($companyData['phone']);
            }
            
            // Ensure company type is properly formatted
            if (isset($companyData['company_type'])) {
                $companyType = $companyData['company_type'];
                
                // Get the actual CompanyType model to access the translatable fields
                $companyTypeModel = CompanyType::find($companyType['id']);
                if ($companyTypeModel) {
                    // Decode the JSON type_name field
                    $typeName = is_string($companyTypeModel->type_name) 
                        ? json_decode($companyTypeModel->type_name, true) 
                        : $companyTypeModel->type_name;
                    
                    $companyData['company_type'] = [
                        'id' => $companyType['id'],
                        'slug' => $companyTypeModel->slug ?? $companyType['type_name'],
                        'title' => $typeName ?? [
                            'az' => $this->getCompanyTypeTitle($companyType['type_name'], 'az'),
                            'en' => $this->getCompanyTypeTitle($companyType['type_name'], 'en'),
                            'ru' => $this->getCompanyTypeTitle($companyType['type_name'], 'ru')
                        ]
                    ];
                } else {
                    // Fallback to old method if model not found
                    $companyData['company_type'] = [
                        'id' => $companyType['id'],
                        'slug' => $companyType['type_name'],
                        'title' => [
                            'az' => $this->getCompanyTypeTitle($companyType['type_name'], 'az'),
                            'en' => $this->getCompanyTypeTitle($companyType['type_name'], 'en'),
                            'ru' => $this->getCompanyTypeTitle($companyType['type_name'], 'ru')
                        ]
                    ];
                }
            }
            
            // Add site field from website if not present
            if (!isset($companyData['site']) && isset($companyData['website'])) {
                $companyData['site'] = $companyData['website'];
            }
            
            return $companyData;
        });
        
        return response()->json([
            'data' => $companies->items(),
            'pagination' => [
                'current_page' => $companies->currentPage(),
                'total_pages' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total()
            ]
        ]);
    }
    
    public function show($lang = null, $typeSlug = null, $companySlug = null)
    {
        // If only two parameters are passed, they are type and company slug
        if ($companySlug === null && $typeSlug !== null) {
            $companySlug = $typeSlug;
            $typeSlug = $lang;
            $lang = null;
        }
        
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        // Map type slugs to type names
        $typeMapping = [
            'banks' => 'bank',
            'insurance' => 'insurance',
            'credit-organizations' => 'credit_organization'
        ];
        
        $typeName = $typeMapping[$typeSlug] ?? $typeSlug;
        
        // Find the company type
        $companyType = CompanyType::where('type_name', $typeName)->first();
        if (!$companyType) {
            return response()->json(['message' => 'Company type not found'], 404);
        }
        
        // Find the company
        $company = Company::where('slug', $companySlug)
            ->where('company_type_id', $companyType->id)
            ->active()
            ->first();
            
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        
        // Get all EAV attributes for this company
        $companyData = $company->toArrayWithAttributes();
        
        // Parse translatable fields
        if (isset($companyData['about']) && is_array($companyData['about'])) {
            $companyData['about'] = $companyData['about'][$lang] ?? $companyData['about']['az'] ?? '';
        }
        
        // Format addresses if they exist
        if (isset($companyData['addresses'])) {
            $addresses = is_string($companyData['addresses']) ? json_decode($companyData['addresses'], true) : $companyData['addresses'];
            $companyData['addresses'] = is_array($addresses) ? $addresses : [$addresses];
        }
        
        // Format phones if they exist
        if (isset($companyData['phone'])) {
            $companyData['phones'] = [$companyData['phone']];
        }
        
        // Add site field from website if not present
        if (!isset($companyData['site']) && isset($companyData['website'])) {
            $companyData['site'] = $companyData['website'];
        }
        
        // Ensure company type is properly formatted
        if (isset($companyData['company_type'])) {
            $companyType = $companyData['company_type'];
            
            // Get the actual CompanyType model to access the translatable fields
            $companyTypeModel = CompanyType::find($companyType['id']);
            if ($companyTypeModel) {
                // Decode the JSON type_name field
                $typeName = is_string($companyTypeModel->type_name) 
                    ? json_decode($companyTypeModel->type_name, true) 
                    : $companyTypeModel->type_name;
                
                $companyData['company_type'] = [
                    'id' => $companyType['id'],
                    'slug' => $companyTypeModel->slug ?? $typeSlug,
                    'title' => $typeName ?? [
                        'az' => $this->getCompanyTypeTitle($companyType['type_name'], 'az'),
                        'en' => $this->getCompanyTypeTitle($companyType['type_name'], 'en'),
                        'ru' => $this->getCompanyTypeTitle($companyType['type_name'], 'ru')
                    ]
                ];
            } else {
                // Fallback to old method if model not found
                $companyData['company_type'] = [
                    'id' => $companyType['id'],
                    'slug' => $typeSlug,
                    'title' => [
                        'az' => $this->getCompanyTypeTitle($companyType['type_name'], 'az'),
                        'en' => $this->getCompanyTypeTitle($companyType['type_name'], 'en'),
                        'ru' => $this->getCompanyTypeTitle($companyType['type_name'], 'ru')
                    ]
                ];
            }
        }
        
        // Get company entities (branches, products, etc.)
        $companyData['entities'] = $company->getEntitiesWithAttributes();
        
        return response()->json([
            'data' => $companyData
        ]);
    }
    
    public function showByType($lang = null, $type = null, $slug = null)
    {
        // Set locale if provided
        if ($lang) {
            app()->setLocale($lang);
        }
        $lang = $lang ?? app()->getLocale();
        
        // No mapping needed - use Azerbaijani slugs directly
        $dbSlug = $type;
        
        // Find the company type by slug
        $companyType = CompanyType::where('slug', $dbSlug)->first();
        if (!$companyType) {
            return response()->json(['message' => 'Company type not found'], 404);
        }
        
        // Find the company
        $company = Company::where('slug', $slug)
            ->where('company_type_id', $companyType->id)
            ->active()
            ->first();
            
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        
        // Get all EAV attributes for this company
        $companyData = $company->toArrayWithAttributes();
        
        // Parse translatable fields
        if (isset($companyData['about']) && is_array($companyData['about'])) {
            $companyData['about'] = $companyData['about'][$lang] ?? $companyData['about']['az'] ?? '';
        }
        
        // Format addresses if they exist
        if (isset($companyData['addresses'])) {
            $addresses = is_string($companyData['addresses']) ? json_decode($companyData['addresses'], true) : $companyData['addresses'];
            $companyData['addresses'] = is_array($addresses) ? $addresses : [$addresses];
        }
        
        // Format phones if they exist
        if (isset($companyData['phone'])) {
            $companyData['phones'] = [$companyData['phone']];
        }
        
        // Add site field from website if not present
        if (!isset($companyData['site']) && isset($companyData['website'])) {
            $companyData['site'] = $companyData['website'];
        }
        
        // Ensure company type is properly formatted
        if (isset($companyData['company_type'])) {
            $companyTypeData = $companyData['company_type'];
            
            // Get the actual CompanyType model to access the translatable fields
            $companyTypeModel = CompanyType::find($companyTypeData['id']);
            if ($companyTypeModel) {
                // Decode the JSON type_name field
                $typeName = is_string($companyTypeModel->type_name) 
                    ? json_decode($companyTypeModel->type_name, true) 
                    : $companyTypeModel->type_name;
                
                $companyData['company_type'] = [
                    'id' => $companyTypeData['id'],
                    'slug' => $companyTypeModel->slug ?? $type,
                    'title' => $typeName ?? [
                        'az' => $this->getCompanyTypeTitle($companyTypeData['type_name'], 'az'),
                        'en' => $this->getCompanyTypeTitle($companyTypeData['type_name'], 'en'),
                        'ru' => $this->getCompanyTypeTitle($companyTypeData['type_name'], 'ru')
                    ]
                ];
            } else {
                // Fallback to old method if model not found
                $companyData['company_type'] = [
                    'id' => $companyTypeData['id'],
                    'slug' => $type,
                    'title' => [
                        'az' => $this->getCompanyTypeTitle($companyTypeData['type_name'], 'az'),
                        'en' => $this->getCompanyTypeTitle($companyTypeData['type_name'], 'en'),
                        'ru' => $this->getCompanyTypeTitle($companyTypeData['type_name'], 'ru')
                    ]
                ];
            }
        }
        
        // Get company entities (branches, products, etc.)
        $companyData['entities'] = $company->getEntitiesWithAttributes();
        
        return response()->json([
            'data' => $companyData
        ]);
    }
    
    /**
     * Get localized company type title
     */
    private function getCompanyTypeTitle($typeName, $locale)
    {
        $titles = [
            'bank' => [
                'az' => 'Banklar',
                'en' => 'Banks',
                'ru' => 'Банки'
            ],
            'insurance' => [
                'az' => 'Sığorta şirkətləri',
                'en' => 'Insurance Companies',
                'ru' => 'Страховые компании'
            ],
            'credit_organization' => [
                'az' => 'Kredit təşkilatları',
                'en' => 'Credit Organizations',
                'ru' => 'Кредитные организации'
            ],
            'investment' => [
                'az' => 'İnvestisiya şirkətləri',
                'en' => 'Investment Companies',
                'ru' => 'Инвестиционные компании'
            ],
            'leasing' => [
                'az' => 'Lizinq şirkətləri',
                'en' => 'Leasing Companies',
                'ru' => 'Лизинговые компании'
            ],
            'payment_system' => [
                'az' => 'Ödəniş sistemləri',
                'en' => 'Payment Systems',
                'ru' => 'Платежные системы'
            ]
        ];
        
        return $titles[$typeName][$locale] ?? $typeName;
    }
}