<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EntityController extends Controller
{
    /**
     * Get all entities of a specific type (e.g., 'cash_loan', 'auto_loan', 'mortgage', etc.)
     */
    public function index(Request $request, $locale = 'az', $entityType = null)
    {
        // Set locale
        app()->setLocale($locale);
        
        // Get parameters
        $perPage = $request->get('per_page', 20);
        $search = $request->get('search');
        $companyId = $request->get('company_id');
        $minAmount = $request->get('min_amount');
        $maxAmount = $request->get('max_amount');
        $sortBy = $request->get('sort_by', 'interest_rate');
        
        // Convert entity type slug to proper format (e.g., 'cash-loans' -> 'cash_loan')
        $entityType = str_replace('-', '_', rtrim($entityType, 's'));
        
        // Get entity type info
        $entityTypeInfo = DB::table('company_entity_types')
            ->where('entity_name', $entityType)
            ->first();
            
        if (!$entityTypeInfo) {
            return response()->json([
                'status' => 'error',
                'message' => 'Entity type not found',
                'data' => []
            ], 404);
        }
        
        // Query to get entities with their attributes from the view
        $query = DB::table('v_company_entities as ve')
            ->select([
                've.entity_id',
                've.entity_name',
                've.entity_code',
                've.entity_type',
                've.company_id',
                've.company_name',
                've.company_slug',
                've.company_type',
                've.is_active',
                've.display_order'
            ])
            ->where('ve.entity_type', $entityType)
            ->where('ve.is_active', true);
        
        // Apply filters
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('ve.entity_name', 'like', "%{$search}%")
                  ->orWhere('ve.company_name', 'like', "%{$search}%");
            });
        }
        
        if ($companyId) {
            $query->where('ve.company_id', $companyId);
        }
        
        // Get entities
        $entities = $query->orderBy('ve.display_order')->paginate($perPage);
        
        // For each entity, get its attributes from the EAV system
        $entities->getCollection()->transform(function ($entity) use ($locale, $entityType) {
            // Parse entity_name if it's JSON
            $entityName = $entity->entity_name;
            if ($this->isJson($entityName)) {
                $decoded = json_decode($entityName, true);
                if (is_array($decoded)) {
                    $entityName = $decoded[$locale] ?? $decoded['az'] ?? $decoded['en'] ?? $entityName;
                }
            }
            
            // Parse company_name if it's JSON
            $companyName = $entity->company_name;
            if ($this->isJson($companyName)) {
                $decoded = json_decode($companyName, true);
                if (is_array($decoded)) {
                    $companyName = $decoded[$locale] ?? $decoded['az'] ?? $decoded['en'] ?? $companyName;
                }
            }
            
            // Get all attributes for this entity
            $attributes = DB::table('company_entity_attribute_values as av')
                ->join('company_attribute_definitions as a', 'av.attribute_definition_id', '=', 'a.id')
                ->where('av.entity_id', $entity->entity_id)
                ->select('a.attribute_name', 
                    'av.value_text',
                    'av.value_number', 
                    'av.value_date',
                    'av.value_json')
                ->get()
                ->mapWithKeys(function ($item) use ($locale) {
                    // Determine which value field has data
                    $value = $item->value_text ?? $item->value_number ?? $item->value_date ?? $item->value_json;
                    
                    // Parse JSON values for translatable attributes
                    if ($value && $this->isJson($value)) {
                        $decoded = json_decode($value, true);
                        if (is_array($decoded)) {
                            $value = $decoded[$locale] ?? $decoded['az'] ?? $decoded['en'] ?? $value;
                        }
                    }
                    
                    return [$item->attribute_name => $value];
                })
                ->toArray();
            
            // Build the response structure
            $result = [
                'id' => $entity->entity_id,
                'entity_id' => $entity->entity_id,
                'entity_name' => $entityName,
                'entity_type' => $entity->entity_type,
                'company_id' => $entity->company_id,
                'company_name' => $companyName,
                'company_slug' => $entity->company_slug,
            ];
            
            // Add common credit attributes - check various attribute name formats
            if (in_array($entityType, ['cash_loan', 'auto_loan', 'mortgage', 'student_loan', 'business_loan', 'credit_loan'])) {
                // Interest rate - try different attribute names
                $interestRate = $attributes['Interest Rate %'] ?? 
                               $attributes['interest_rate'] ?? 
                               $attributes['annual_interest_rate'] ?? 
                               null;
                $result['interest_rate'] = $interestRate !== null ? (float)$interestRate : null;
                
                // Amounts
                $result['min_amount'] = isset($attributes['Minimum Amount']) ? (float)$attributes['Minimum Amount'] : 
                                       (isset($attributes['min_amount']) ? (float)$attributes['min_amount'] : null);
                                       
                $result['max_amount'] = isset($attributes['Maximum Amount']) ? (float)$attributes['Maximum Amount'] :
                                       (isset($attributes['max_amount']) ? (float)$attributes['max_amount'] : null);
                
                // Terms
                $result['min_term_months'] = isset($attributes['Min Term (months)']) ? (int)$attributes['Min Term (months)'] :
                                            (isset($attributes['min_term_months']) ? (int)$attributes['min_term_months'] : null);
                                            
                $result['max_term_months'] = isset($attributes['Max Term (months)']) ? (int)$attributes['Max Term (months)'] :
                                            (isset($attributes['max_term_months']) ? (int)$attributes['max_term_months'] : null);
                
                // Other attributes
                $result['loan_type'] = $attributes['Loan Type'] ?? $attributes['loan_type'] ?? $entityType;
                $result['requirements'] = $attributes['Requirements'] ?? $attributes['requirements'] ?? null;
                $result['processing_time'] = $attributes['Processing Time'] ?? $attributes['processing_time'] ?? null;
                $result['commission'] = isset($attributes['Commission']) ? (float)$attributes['Commission'] :
                                       (isset($attributes['commission']) ? (float)$attributes['commission'] : null);
                $result['monthly_payment'] = isset($attributes['Monthly Payment']) ? (float)$attributes['Monthly Payment'] :
                                            (isset($attributes['monthly_payment']) ? (float)$attributes['monthly_payment'] : null);
            }
            
            // Add deposit-specific attributes
            if ($entityType === 'deposit') {
                $result['interest_rate'] = $attributes['interest_rate'] ?? null;
                $result['min_amount'] = isset($attributes['min_amount']) ? (float)$attributes['min_amount'] : null;
                $result['term_months'] = isset($attributes['term_months']) ? (int)$attributes['term_months'] : null;
                $result['currency'] = $attributes['currency'] ?? 'AZN';
                $result['deposit_type'] = $attributes['deposit_type'] ?? null;
            }
            
            // Add all other attributes
            $result['attributes'] = $attributes;
            
            return $result;
        });
        
        return response()->json([
            'status' => 'success',
            'data' => $entities->items(),
            'meta' => [
                'current_page' => $entities->currentPage(),
                'last_page' => $entities->lastPage(),
                'per_page' => $entities->perPage(),
                'total' => $entities->total(),
                'entity_type' => $entityType,
                'entity_type_name' => $entityTypeInfo->entity_name
            ]
        ]);
    }
    
    /**
     * Get all entity types with display names and icons
     */
    public function getEntityTypes(Request $request)
    {
        try {
            // Get entity types from database
            $dbEntityTypes = DB::table('company_entity_types as cet')
                ->select('cet.entity_name', 'cet.description', 'ct.type_name as parent_type')
                ->leftJoin('company_types as ct', 'cet.parent_company_type_id', '=', 'ct.id')
                ->orderBy('cet.display_order')
                ->get()
                ->map(function ($entityType) {
                    return [
                        'entity_name' => $entityType->entity_name,
                        'display_name' => $this->getEntityTypeDisplayName($entityType->entity_name),
                        'description' => $entityType->description,
                        'parent_type' => $entityType->parent_type,
                        'icon' => $this->getEntityTypeIcon($entityType->entity_name),
                    ];
                });

            // Add virtual entity types used for comparison grouping
            $virtualEntityTypes = collect([
                [
                    'entity_name' => 'cash_loans',
                    'display_name' => $this->getEntityTypeDisplayName('cash_loans'),
                    'description' => 'Cash loan products',
                    'parent_type' => 'Credit Organizations',
                    'icon' => $this->getEntityTypeIcon('cash_loans'),
                ],
                [
                    'entity_name' => 'education_loans',
                    'display_name' => $this->getEntityTypeDisplayName('education_loans'),
                    'description' => 'Education loan products',
                    'parent_type' => 'Credit Organizations',
                    'icon' => $this->getEntityTypeIcon('education_loans'),
                ],
                [
                    'entity_name' => 'business_loans',
                    'display_name' => $this->getEntityTypeDisplayName('business_loans'),
                    'description' => 'Business loan products',
                    'parent_type' => 'Credit Organizations',
                    'icon' => $this->getEntityTypeIcon('business_loans'),
                ],
                [
                    'entity_name' => 'credit_lines',
                    'display_name' => $this->getEntityTypeDisplayName('credit_lines'),
                    'description' => 'Credit line products',
                    'parent_type' => 'Credit Organizations',
                    'icon' => $this->getEntityTypeIcon('credit_lines'),
                ],
                [
                    'entity_name' => 'auto_loans',
                    'display_name' => $this->getEntityTypeDisplayName('auto_loans'),
                    'description' => 'Auto loan products',
                    'parent_type' => 'Credit Organizations',
                    'icon' => $this->getEntityTypeIcon('auto_loans'),
                ],
                [
                    'entity_name' => 'mortgage_loans',
                    'display_name' => $this->getEntityTypeDisplayName('mortgage_loans'),
                    'description' => 'Mortgage loan products',
                    'parent_type' => 'Credit Organizations',
                    'icon' => $this->getEntityTypeIcon('mortgage_loans'),
                ],
                [
                    'entity_name' => 'pawnshop_loans',
                    'display_name' => $this->getEntityTypeDisplayName('pawnshop_loans'),
                    'description' => 'Pawnshop loan products',
                    'parent_type' => 'Credit Organizations',
                    'icon' => $this->getEntityTypeIcon('pawnshop_loans'),
                ],
            ]);

            $allEntityTypes = $dbEntityTypes->concat($virtualEntityTypes);

            return response()->json([
                'status' => 'success',
                'data' => $allEntityTypes
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching entity types: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch entity types'
            ], 500);
        }
    }

    /**
     * Get display names for entity types (translatable)
     */
    private function getEntityTypeDisplayName($entityType)
    {
        $displayNames = [
            // Mapped loan types (used for comparison grouping)
            'cash_loans' => ['az' => 'Nağd kreditlər', 'en' => 'Cash Loans', 'ru' => 'Наличные кредиты'],
            'education_loans' => ['az' => 'Təhsil kreditləri', 'en' => 'Education Loans', 'ru' => 'Образовательные кредиты'],
            'business_loans' => ['az' => 'Biznes kreditləri', 'en' => 'Business Loans', 'ru' => 'Бизнес кредиты'],
            'credit_lines' => ['az' => 'Kredit xətləri', 'en' => 'Credit Lines', 'ru' => 'Кредитные линии'],
            'pawnshop_loans' => ['az' => 'Girov kreditləri', 'en' => 'Pawnshop Loans', 'ru' => 'Ломбардные кредиты'],
            'auto_loans' => ['az' => 'Avto kreditlər', 'en' => 'Auto Loans', 'ru' => 'Автокредиты'],
            'mortgage_loans' => ['az' => 'İpoteka kreditləri', 'en' => 'Mortgage Loans', 'ru' => 'Ипотечные кредиты'],
            
            // Raw loan types (from loan_type attribute) 
            'cash' => ['az' => 'Nağd kreditlər', 'en' => 'Cash Loans', 'ru' => 'Наличные кредиты'],
            'student' => ['az' => 'Tələbə krediti', 'en' => 'Student Loans', 'ru' => 'Студенческие кредиты'],
            'business' => ['az' => 'Biznes krediti', 'en' => 'Business Loans', 'ru' => 'Бизнес кредиты'],
            'auto' => ['az' => 'Avto kreditlər', 'en' => 'Auto Loans', 'ru' => 'Автокредиты'],
            'mortgage' => ['az' => 'İpoteka', 'en' => 'Mortgage', 'ru' => 'Ипотека'],
            'express' => ['az' => 'Ekspres kreditlər', 'en' => 'Express Loans', 'ru' => 'Экспресс кредиты'],
            'pawn' => ['az' => 'Girov kreditləri', 'en' => 'Pawn Loans', 'ru' => 'Ломбардные кредиты'],
            
            // Entity types from DB
            'credit_loan' => ['az' => 'Kreditlər', 'en' => 'Credit Loans', 'ru' => 'Кредиты'],
            'credit_card' => ['az' => 'Kredit kartları', 'en' => 'Credit Cards', 'ru' => 'Кредитные карты'],
            'deposit' => ['az' => 'Depozitlər', 'en' => 'Deposits', 'ru' => 'Депозиты'],
            'insurance_product' => ['az' => 'Sığorta məhsulları', 'en' => 'Insurance Products', 'ru' => 'Страховые продукты'],
            'branch' => ['az' => 'Filiallar', 'en' => 'Branches', 'ru' => 'Филиалы'],
            'atm' => ['az' => 'Bankomat', 'en' => 'ATMs', 'ru' => 'Банкоматы'],
            'loan' => ['az' => 'Kreditlər', 'en' => 'Loans', 'ru' => 'Кредиты'],
        ];

        return $displayNames[$entityType] ?? ['az' => $entityType, 'en' => $entityType, 'ru' => $entityType];
    }

    /**
     * Get icons for entity types
     */
    private function getEntityTypeIcon($entityType)
    {
        $icons = [
            // Mapped loan types (used for comparison grouping)
            'cash_loans' => 'Banknote',
            'education_loans' => 'GraduationCap',
            'business_loans' => 'Briefcase',
            'credit_lines' => 'Zap',
            'pawnshop_loans' => 'Shield',
            'auto_loans' => 'Car',
            'mortgage_loans' => 'Home',
            
            // Raw loan types (from loan_type attribute)
            'cash' => 'Banknote',
            'student' => 'GraduationCap',
            'business' => 'Briefcase', 
            'auto' => 'Car',
            'mortgage' => 'Home',
            'express' => 'Zap',
            'pawn' => 'Shield',
            
            // Entity types from DB
            'credit_loan' => 'CreditCard',
            'credit_card' => 'CreditCard',
            'deposit' => 'Building2',
            'insurance_product' => 'Shield',
            'branch' => 'MapPin',
            'atm' => 'DollarSign',
            'loan' => 'CreditCard',
        ];

        return $icons[$entityType] ?? 'Circle';
    }
    
    /**
     * Get entities by their IDs for comparison
     */
    public function getEntitiesByIds(Request $request)
    {
        $ids = $request->input('ids', '');
        $locale = $request->route('locale', 'az');
        
        if (empty($ids)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No IDs provided'
            ], 400);
        }
        
        $entityIds = is_array($ids) ? $ids : explode(',', $ids);
        $entityIds = array_map('intval', $entityIds);
        
        // Get entities with their company and type information
        $entities = DB::table('company_entities as ce')
            ->join('companies as c', 'ce.company_id', '=', 'c.id')
            ->join('company_entity_types as cet', 'ce.entity_type_id', '=', 'cet.id')
            ->whereIn('ce.id', $entityIds)
            ->select(
                'ce.id as entity_id',
                'ce.entity_name',
                'ce.entity_code',
                'cet.entity_name as entity_type',
                'c.id as company_id',
                'c.name as company_name',
                'c.slug as company_slug'
            )
            ->get();
        
        // Process each entity
        $entities->transform(function ($entity) use ($locale) {
            // Parse entity_name if it's JSON
            $entityName = $entity->entity_name;
            if ($this->isJson($entityName)) {
                $decoded = json_decode($entityName, true);
                if (is_array($decoded)) {
                    $entityName = $decoded[$locale] ?? $decoded['az'] ?? $decoded['en'] ?? $entityName;
                }
            }
            
            // Parse company_name if it's JSON
            $companyName = $entity->company_name;
            if ($this->isJson($companyName)) {
                $decoded = json_decode($companyName, true);
                if (is_array($decoded)) {
                    $companyName = $decoded[$locale] ?? $decoded['az'] ?? $decoded['en'] ?? $companyName;
                }
            }
            
            // Get all attributes for this entity
            $attributes = DB::table('company_entity_attribute_values as av')
                ->join('company_attribute_definitions as a', 'av.attribute_definition_id', '=', 'a.id')
                ->where('av.entity_id', $entity->entity_id)
                ->select('a.attribute_key', 'av.value_text', 'av.value_number', 'av.value_date')
                ->get()
                ->mapWithKeys(function ($attr) {
                    $value = $attr->value_text ?? $attr->value_number ?? $attr->value_date;
                    return [$attr->attribute_key => $value];
                })
                ->toArray();
            
            return [
                'entity_id' => $entity->entity_id,
                'entity_name' => $entityName,
                'entity_type' => $entity->entity_type,
                'company_id' => $entity->company_id,
                'company_name' => $companyName,
                'company_slug' => $entity->company_slug,
                'attributes' => $attributes,
                // Include common attributes at top level for easy access
                'interest_rate' => isset($attributes['interest_rate']) ? (float)$attributes['interest_rate'] : 
                                  (isset($attributes['Interest Rate %']) ? (float)$attributes['Interest Rate %'] : null),
                'min_amount' => isset($attributes['min_amount']) ? (float)$attributes['min_amount'] :
                               (isset($attributes['Minimum Amount']) ? (float)$attributes['Minimum Amount'] : null),
                'max_amount' => isset($attributes['max_amount']) ? (float)$attributes['max_amount'] :
                               (isset($attributes['Maximum Amount']) ? (float)$attributes['Maximum Amount'] : null),
                'loan_type' => $attributes['loan_type'] ?? $attributes['Loan Type'] ?? null,
            ];
        });
        
        return response()->json([
            'status' => 'success',
            'data' => $entities
        ]);
    }
    
    /**
     * Check if a string is valid JSON
     */
    private function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}