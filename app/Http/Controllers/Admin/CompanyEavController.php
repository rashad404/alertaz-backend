<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyType;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanyEavController extends Controller
{
    protected $imageService;

    public function __construct(ImageUploadService $imageService)
    {
        $this->imageService = $imageService;
    }
    /**
     * Display a listing of companies with EAV data
     */
    public function index(Request $request)
    {
        $query = Company::query();

        // Search by company name or slug
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Filter by type
        if ($request->has('type_id') && $request->type_id) {
            $query->where('company_type_id', $request->type_id);
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 20);
        $companies = $query->paginate($perPage);

        // Load company types manually due to relationship issue
        $companyTypeIds = $companies->pluck('company_type_id')->unique()->filter();
        $companyTypes = CompanyType::whereIn('id', $companyTypeIds)->get()->keyBy('id');

        // Add EAV attributes, entity counts and company type for each company
        $companies->getCollection()->transform(function ($company) use ($companyTypes) {
            // Get EAV attributes from the database
            $company->eav_attributes = $this->getCompanyAttributes($company->id);

            // Get entity counts
            $company->entity_counts = $this->getCompanyEntityCounts($company->id);

            // Manually attach company type
            if ($company->company_type_id && isset($companyTypes[$company->company_type_id])) {
                $type = $companyTypes[$company->company_type_id];
                // Parse type_name if it's JSON
                if (is_string($type->type_name)) {
                    try {
                        $typeName = json_decode($type->type_name, true);
                        $type->type_name = $typeName;
                    } catch (\Exception $e) {
                        // Keep as is if not valid JSON
                    }
                }
                $company->companyType = $type;
            }

            // Fix logo path to include /storage/ prefix
            if ($company->logo && !str_starts_with($company->logo, '/storage/') && !str_starts_with($company->logo, 'http')) {
                $company->logo = '/storage/' . $company->logo;
            }

            return $company;
        });

        return response()->json([
            'status' => 'success',
            'data' => $companies,
        ]);
    }

    /**
     * Store a newly created company
     */
    public function store(Request $request)
    {
        $request->validate([
            'company_type_id' => 'required|exists:company_types,id',
            'name' => 'required|string',
            'logo' => 'nullable|image|max:2048',
            'attributes' => 'nullable', // Can be array or JSON string
        ]);

        DB::beginTransaction();
        try {
            // Generate slug from name
            $slug = $this->generateUniqueSlug($request->name);

            $data = [
                'name' => $request->name,
                'slug' => $slug,
                'company_type_id' => $request->company_type_id,
                'is_active' => $request->get('is_active', true),
                'display_order' => $request->get('display_order', 0),
            ];

            // Handle logo upload
            if ($request->hasFile('logo')) {
                $path = $this->imageService->upload($request->file('logo'), 'companies');
                $data['logo'] = $path;
            }

            // Create basic company record
            $company = Company::create($data);
            
            // Save EAV attributes
            if ($request->has('attributes')) {
                $attributes = $request->input('attributes');

                // Handle JSON string from FormData
                if (is_string($attributes)) {
                    $attributes = json_decode($attributes, true);
                }

                \Log::info('Store request attributes debug', [
                    'company_id' => $company->id,
                    'attributes_type' => gettype($attributes),
                    'attributes_raw' => $attributes,
                    'is_array' => is_array($attributes)
                ]);

                if (is_array($attributes)) {
                    $this->saveCompanyAttributes($company->id, $request->company_type_id, $attributes);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Company created successfully',
                'data' => $company,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create company: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified company
     */
    public function show($id)
    {
        $company = Company::with(['companyType'])->findOrFail($id);
        
        // Get EAV attributes
        $company->eav_attributes = $this->getCompanyAttributes($company->id);

        // Get entities with their attributes
        $company->entities = $this->getCompanyEntities($company->id);

        // Fix logo path to include /storage/ prefix
        if ($company->logo && !str_starts_with($company->logo, '/storage/') && !str_starts_with($company->logo, 'http')) {
            $company->logo = '/storage/' . $company->logo;
        }

        return response()->json([
            'status' => 'success',
            'data' => $company,
        ]);
    }

    /**
     * Update the specified company
     */
    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        $request->validate([
            'company_type_id' => 'sometimes|exists:company_types,id',
            'name' => 'sometimes|string',
            'logo' => 'nullable|image|max:2048',
            'attributes' => 'nullable', // Can be array or JSON string
        ]);

        DB::beginTransaction();
        try {
            // Update basic company fields
            $updateData = [];
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
                $updateData['slug'] = $this->generateUniqueSlug($request->name, $company->id);
            }
            if ($request->has('company_type_id')) {
                $updateData['company_type_id'] = $request->company_type_id;
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->boolean('is_active');
            }
            if ($request->has('display_order')) {
                $updateData['display_order'] = $request->display_order;
            }

            // Handle logo upload
            if ($request->hasFile('logo')) {
                // Delete old logo if exists
                if ($company->logo) {
                    $this->imageService->delete($company->logo);
                }

                $path = $this->imageService->upload($request->file('logo'), 'companies');
                $updateData['logo'] = $path;
            }

            if (!empty($updateData)) {
                $company->update($updateData);
            }
            
            // Update EAV attributes
            if ($request->has('attributes')) {
                $attributes = $request->input('attributes');

                // Handle JSON string from FormData
                if (is_string($attributes)) {
                    $attributes = json_decode($attributes, true);
                }

                \Log::info('Update request attributes debug', [
                    'company_id' => $company->id,
                    'attributes_type' => gettype($attributes),
                    'attributes_raw' => $attributes,
                    'is_array' => is_array($attributes)
                ]);

                if (is_array($attributes)) {
                    $this->updateCompanyAttributes($company->id, $company->company_type_id, $attributes);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Company updated successfully',
                'data' => $company->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update company: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified company
     */
    public function destroy($id)
    {
        try {
            $company = Company::findOrFail($id);
            $company->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Company deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete company: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle company status
     */
    public function toggleStatus($id)
    {
        $company = Company::findOrFail($id);
        $company->is_active = !$company->is_active;
        $company->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Status updated successfully',
            'data' => ['is_active' => $company->is_active],
        ]);
    }

    /**
     * Toggle company featured status
     */
    public function toggleFeatured($id)
    {
        $company = Company::findOrFail($id);
        $company->is_featured = !$company->is_featured;
        $company->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Featured status updated successfully',
            'data' => ['is_featured' => $company->is_featured],
        ]);
    }

    /**
     * Get single entity
     */
    public function getEntity($companyId, $entityId)
    {
        try {
            $entity = DB::table('company_entities as ce')
                ->join('company_entity_types as cet', 'ce.entity_type_id', '=', 'cet.id')
                ->where('ce.id', $entityId)
                ->where('ce.company_id', $companyId)
                ->select('ce.*', 'cet.entity_name as entity_type')
                ->first();
                
            if (!$entity) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Entity not found',
                ], 404);
            }
            
            // Get entity attributes from view
            $attributesData = DB::table('v_entity_attributes')
                ->where('entity_id', $entityId)
                ->get();
                
            // Build attributes array
            $decodedAttributes = [];
            foreach ($attributesData as $attr) {
                $value = $attr->attribute_value;
                
                // Decode JSON values
                if ($this->isJson($value)) {
                    $decodedAttributes[$attr->attribute_key] = json_decode($value, true);
                } else {
                    $decodedAttributes[$attr->attribute_key] = $value;
                }
            }
            
            // Build response with proper field names for frontend
            $responseData = [
                'id' => $entity->id,
                'name' => $entity->entity_name,  // Map entity_name to name
                'code' => $entity->entity_code,  // Map entity_code to code
                'entity_type' => $entity->entity_type,
                'is_active' => $entity->is_active,
                'display_order' => $entity->display_order,
                'attributes' => $decodedAttributes,
            ];
            
            return response()->json([
                'status' => 'success',
                'data' => $responseData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch entity: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get company entities
     */
    public function getEntities($companyId, Request $request)
    {
        $company = Company::findOrFail($companyId);
        
        // Get entities from the proper EAV tables
        $entities = $this->getCompanyEntities($companyId);
        
        return response()->json([
            'status' => 'success',
            'data' => $entities,
        ]);
    }

    /**
     * Create company entity
     */
    public function createEntity($companyId, Request $request)
    {
        $request->validate([
            'entity_type' => 'required|string',
            'name' => 'required',
        ]);
        
        DB::beginTransaction();
        try {
            $company = Company::findOrFail($companyId);
            
            // Get entity type ID
            $entityType = DB::table('company_entity_types')
                ->where('entity_name', $request->entity_type)
                ->first();
                
            if (!$entityType) {
                // Create entity type if it doesn't exist
                $entityTypeId = DB::table('company_entity_types')->insertGetId([
                    'entity_name' => $request->entity_type,
                    'description' => ucfirst(str_replace('_', ' ', $request->entity_type)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $entityTypeId = $entityType->id;
            }
            
            // Create entity
            $entityId = DB::table('company_entities')->insertGetId([
                'company_id' => $companyId,
                'entity_type_id' => $entityTypeId,
                'entity_name' => $request->name,
                'entity_code' => $request->get('code'),
                'is_active' => $request->get('is_active', true),
                'display_order' => $request->get('display_order', 0),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Save entity attributes
            if ($request->has('attributes')) {
                $attributes = $request->input('attributes');
                if (is_array($attributes)) {
                    $this->saveEntityAttributes($entityId, $request->entity_type, $attributes);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Entity created successfully',
                'data' => [
                    'id' => $entityId,
                    'name' => $request->name,
                    'code' => $request->get('code'),
                    'entity_type' => $request->entity_type,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create entity: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update company entity
     */
    public function updateEntity($companyId, $entityId, Request $request)
    {
        DB::beginTransaction();
        try {
            // Verify company exists
            $company = Company::findOrFail($companyId);
            
            // Verify entity belongs to company
            $entity = DB::table('company_entities')
                ->where('id', $entityId)
                ->where('company_id', $companyId)
                ->first();
                
            if (!$entity) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Entity not found',
                ], 404);
            }
            
            // Update entity
            $updateData = [];
            if ($request->has('name')) {
                $updateData['entity_name'] = $request->name;
            }
            if ($request->has('code')) {
                $updateData['entity_code'] = $request->code;
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }
            if ($request->has('display_order')) {
                $updateData['display_order'] = $request->display_order;
            }
            
            if (!empty($updateData)) {
                $updateData['updated_at'] = now();
                DB::table('company_entities')
                    ->where('id', $entityId)
                    ->update($updateData);
            }
            
            // Update entity attributes
            if ($request->has('attributes')) {
                $attributes = $request->input('attributes');
                
                if (is_array($attributes)) {
                    \Log::info('Updating entity attributes', [
                        'entity_id' => $entityId,
                        'has_attributes' => true,
                        'attributes_count' => count($attributes)
                    ]);
                
                // Get entity type
                $entityTypeRecord = DB::table('company_entity_types')
                    ->where('id', $entity->entity_type_id)
                    ->first();
                    
                \Log::info('Entity type lookup', [
                    'entity_type_id' => $entity->entity_type_id,
                    'found' => $entityTypeRecord ? true : false,
                    'entity_type_name' => $entityTypeRecord ? $entityTypeRecord->entity_name : null
                ]);
                    
                if ($entityTypeRecord) {
                    // Delete existing attributes
                    $deletedCount = DB::table('company_entity_attribute_values')
                        ->where('entity_id', $entityId)
                        ->delete();
                        
                    \Log::info('Deleted existing attributes', ['count' => $deletedCount]);
                        
                    // Save new attributes
                    $this->saveEntityAttributes($entityId, $entityTypeRecord->entity_name, $attributes);
                } else {
                    \Log::warning('Entity type record not found', ['entity_type_id' => $entity->entity_type_id]);
                }
                } else {
                    \Log::info('Attributes not an array', [
                        'attributes_type' => gettype($attributes),
                        'attributes_raw' => $attributes
                    ]);
                }
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Entity updated successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update entity: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete company entity
     */
    public function deleteEntity($companyId, $entityId)
    {
        try {
            $company = Company::findOrFail($companyId);
            $attributes = $this->decodeJson($company->attributes) ?: [];
            
            if (isset($attributes['entities'])) {
                $attributes['entities'] = array_filter($attributes['entities'], function($entity) use ($entityId) {
                    return $entity['id'] !== $entityId;
                });
                $attributes['entities'] = array_values($attributes['entities']); // Reindex
            }
            
            $company->attributes = $attributes;
            $company->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Entity deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete entity: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get company types
     */
    public function getTypes()
    {
        $types = DB::table('company_types')
            ->orderBy('type_name')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $types,
        ]);
    }

    /**
     * Get attribute definitions for a company type
     */
    public function getAttributeDefinitions($companyTypeId)
    {
        $attributes = DB::table('company_attribute_definitions')
            ->leftJoin('company_attribute_groups', 'company_attribute_definitions.attribute_group_id', '=', 'company_attribute_groups.id')
            ->where('company_attribute_definitions.company_type_id', $companyTypeId)
            ->select(
                'company_attribute_definitions.*',
                'company_attribute_groups.group_name',
                'company_attribute_groups.display_order as group_order'
            )
            ->orderBy('company_attribute_groups.display_order')
            ->orderBy('company_attribute_definitions.display_order')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $attributes,
        ]);
    }

    /**
     * Helper methods
     */
    private function generateUniqueSlug($name, $excludeId = null)
    {
        $slug = Str::slug($name);
        $query = Company::where('slug', 'like', $slug . '%');
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        $count = $query->count();
        return $count ? "{$slug}-{$count}" : $slug;
    }

    /**
     * Get company attributes from EAV tables
     */
    private function getCompanyAttributes($companyId)
    {
        try {
            $attributes = DB::table('v_company_attributes')
                ->where('company_id', $companyId)
                ->get()
                ->mapWithKeys(function ($item) {
                    $value = $item->attribute_value;
                    // Try to decode JSON values
                    if (is_string($value) && $this->isJson($value)) {
                        $value = json_decode($value, true);
                    }
                    return [$item->attribute_key => [
                        'value' => $value,
                        'name' => $item->attribute_name,
                        'data_type' => $item->data_type,
                        'is_required' => $item->is_required,
                        'group' => $item->attribute_group
                    ]];
                });

            return $attributes;
        } catch (\Exception $e) {
            // If view doesn't exist or error, return empty collection
            return collect([]);
        }
    }

    /**
     * Get company entity counts from EAV tables
     */
    private function getCompanyEntityCounts($companyId)
    {
        try {
            $entities = DB::table('company_entities')
                ->join('company_entity_types', 'company_entities.entity_type_id', '=', 'company_entity_types.id')
                ->where('company_entities.company_id', $companyId)
                ->where('company_entities.is_active', true)
                ->select('company_entity_types.entity_name')
                ->get()
                ->groupBy('entity_name')
                ->map->count();

            return [
                'branches' => $entities->get('branch', 0),
                'deposits' => $entities->get('deposit', 0),
                'credit_cards' => $entities->get('credit_card', 0),
                'insurance_products' => $entities->get('insurance_product', 0),
                'total' => $entities->sum(),
            ];
        } catch (\Exception $e) {
            return [
                'branches' => 0,
                'deposits' => 0,
                'credit_cards' => 0,
                'insurance_products' => 0,
                'total' => 0,
            ];
        }
    }

    /**
     * Get company entities with their attributes
     */
    private function getCompanyEntities($companyId)
    {
        try {
            $entities = DB::table('v_company_entities')
                ->where('company_id', $companyId)
                ->get()
                ->groupBy('entity_type');

            $result = [];
            foreach ($entities as $entityType => $entityItems) {
                $result[$entityType] = $entityItems->map(function ($entity) {
                    // Get entity attributes
                    $attributes = DB::table('v_entity_attributes')
                        ->where('entity_id', $entity->entity_id)
                        ->get()
                        ->mapWithKeys(function ($attr) {
                            $value = $attr->attribute_value;
                            if (is_string($value) && $this->isJson($value)) {
                                $value = json_decode($value, true);
                            }
                            return [$attr->attribute_key => $value];
                        });

                    return [
                        'id' => $entity->entity_id,
                        'name' => $entity->entity_name,
                        'code' => $entity->entity_code ?? null,
                        'is_active' => $entity->is_active,
                        'display_order' => $entity->display_order,
                        'attributes' => $attributes
                    ];
                })->toArray();
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Save company attributes to EAV tables
     */
    private function saveCompanyAttributes($companyId, $companyTypeId, $attributes)
    {
        \Log::info('Saving company attributes', [
            'company_id' => $companyId,
            'company_type_id' => $companyTypeId,
            'attributes' => $attributes
        ]);
        
        foreach ($attributes as $key => $value) {
            // Skip empty values
            if ($value === '' || $value === null) {
                continue;
            }
            
            // Get attribute definition
            $attrDef = DB::table('company_attribute_definitions')
                ->where('company_type_id', $companyTypeId)
                ->where('attribute_key', $key)
                ->first();

            if (!$attrDef) {
                \Log::warning('Attribute definition not found for new company', [
                    'company_type_id' => $companyTypeId,
                    'attribute_key' => $key
                ]);
                continue;
            }

            $this->saveAttributeValue($companyId, $attrDef, $value);
            
            \Log::info('Saved new attribute', [
                'company_id' => $companyId,
                'attribute_key' => $key,
                'value' => $value
            ]);
        }
    }

    /**
     * Update company attributes in EAV tables
     */
    private function updateCompanyAttributes($companyId, $companyTypeId, $attributes)
    {
        \Log::info('Updating company attributes', [
            'company_id' => $companyId,
            'company_type_id' => $companyTypeId,
            'attributes' => $attributes
        ]);
        
        foreach ($attributes as $key => $value) {
            // Skip empty values
            if ($value === '' || $value === null) {
                continue;
            }
            
            // Get attribute definition
            $attrDef = DB::table('company_attribute_definitions')
                ->where('company_type_id', $companyTypeId)
                ->where('attribute_key', $key)
                ->first();

            if (!$attrDef) {
                \Log::warning('Attribute definition not found', [
                    'company_type_id' => $companyTypeId,
                    'attribute_key' => $key
                ]);
                continue;
            }

            // Delete existing value
            DB::table('company_attribute_values')
                ->where('company_id', $companyId)
                ->where('attribute_definition_id', $attrDef->id)
                ->delete();

            // Insert new value
            $this->saveAttributeValue($companyId, $attrDef, $value);
            
            \Log::info('Saved attribute', [
                'company_id' => $companyId,
                'attribute_key' => $key,
                'value' => $value
            ]);
        }
    }

    /**
     * Save entity attributes
     */
    private function saveEntityAttributes($entityId, $entityType, $attributes)
    {
        \Log::info('saveEntityAttributes called', [
            'entity_id' => $entityId,
            'entity_type' => $entityType,
            'attributes' => $attributes
        ]);
        
        // Get entity type ID
        $entityTypeRecord = DB::table('company_entity_types')
            ->where('entity_name', $entityType)
            ->first();
            
        if (!$entityTypeRecord) {
            \Log::warning('Entity type not found', ['entity_type' => $entityType]);
            return;
        }
        
        foreach ($attributes as $key => $value) {
            // Skip empty values
            if ($value === '' || $value === null) {
                continue;
            }
            
            // Get attribute definition
            $attrDef = DB::table('company_attribute_definitions')
                ->where('entity_type_id', $entityTypeRecord->id)
                ->where('attribute_key', $key)
                ->first();
                
            if (!$attrDef) {
                \Log::info('Creating new attribute definition', [
                    'entity_type_id' => $entityTypeRecord->id,
                    'attribute_key' => $key
                ]);
                // Create attribute definition if it doesn't exist
                $attrDefId = DB::table('company_attribute_definitions')->insertGetId([
                    'entity_type_id' => $entityTypeRecord->id,
                    'attribute_key' => $key,
                    'attribute_name' => ucfirst(str_replace('_', ' ', $key)),
                    'data_type' => $this->inferDataType($value),
                    'is_required' => 0,
                    'display_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $attrDefId = $attrDef->id;
                \Log::info('Found existing attribute definition', [
                    'attribute_key' => $key,
                    'definition_id' => $attrDefId
                ]);
            }
            
            // Prepare data for insertion
            $data = [
                'entity_id' => $entityId,
                'attribute_definition_id' => $attrDefId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            // Store value in appropriate column based on data type
            $dataType = $attrDef ? $attrDef->data_type : $this->inferDataType($value);
            
            switch ($dataType) {
                case 'number':
                case 'decimal':
                    $data['value_number'] = is_numeric($value) ? $value : 0;
                    break;
                case 'date':
                    $data['value_date'] = $value;
                    break;
                case 'boolean':
                    $data['value_number'] = $value ? 1 : 0;
                    break;
                case 'json':
                    $data['value_json'] = is_array($value) || is_object($value) ? json_encode($value) : $value;
                    break;
                default:
                    // string, text, or unknown
                    $data['value_text'] = is_array($value) || is_object($value) ? json_encode($value) : $value;
                    break;
            }
            
            \Log::info('Inserting entity attribute value', [
                'entity_id' => $entityId,
                'attribute_key' => $key,
                'data' => $data
            ]);
            
            try {
                DB::table('company_entity_attribute_values')->insert($data);
                \Log::info('Successfully inserted attribute value');
            } catch (\Exception $e) {
                \Log::error('Failed to insert attribute value', [
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);
            }
        }
    }
    
    /**
     * Infer data type from value
     */
    private function inferDataType($value)
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? 'decimal' : 'number';
        }
        if (is_array($value) || is_object($value)) {
            return 'json';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return 'date';
        }
        return 'string';
    }

    /**
     * Save single attribute value based on data type
     */
    private function saveAttributeValue($companyId, $attrDef, $value)
    {
        $data = [
            'company_id' => $companyId,
            'attribute_definition_id' => $attrDef->id,
            'created_at' => now(),
            'updated_at' => now()
        ];

        // Store value in appropriate column based on data type
        switch ($attrDef->data_type) {
            case 'string':
            case 'text':
                // If value is an array/object (translatable), JSON encode it
                $data['value_text'] = is_array($value) || is_object($value) ? json_encode($value) : $value;
                break;
            case 'number':
            case 'decimal':
                $data['value_number'] = $value;
                break;
            case 'date':
                $data['value_date'] = $value;
                break;
            case 'json':
                $data['value_json'] = is_string($value) ? $value : json_encode($value);
                break;
            case 'boolean':
                $data['value_number'] = $value ? 1 : 0;
                break;
        }

        DB::table('company_attribute_values')->insert($data);
    }

    /**
     * Check if string is valid JSON
     */
    private function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function decodeJson($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }
        return $value;
    }

    private function getEntitiesFromAttributes($attributes)
    {
        if (!is_array($attributes) || !isset($attributes['entities'])) {
            return [];
        }
        
        return $attributes['entities'];
    }
}