<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Company extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'logo',
        'company_type_id',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the company type
     */
    public function companyType(): BelongsTo
    {
        return $this->belongsTo(CompanyType::class, 'company_type_id', 'id');
    }

    /**
     * Get the company's entities (branches, products, etc.)
     */
    public function entities(): HasMany
    {
        return $this->hasMany(CompanyEntity::class);
    }

    /**
     * Get all attributes for this company
     */
    public function getAttributesData()
    {
        return DB::table('v_company_attributes')
            ->where('company_id', $this->id)
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->attribute_key => $item->attribute_value];
            });
    }

    /**
     * Get a specific attribute value
     */
    public function getAttribute($key)
    {
        // First check if it's a model attribute
        if (array_key_exists($key, $this->attributes)) {
            return parent::getAttribute($key);
        }

        // Otherwise, get from EAV attributes
        $result = DB::table('v_company_attributes')
            ->where('company_id', $this->id)
            ->where('attribute_key', $key)
            ->value('attribute_value');

        // If it's JSON, decode it
        if ($result && $this->isJson($result)) {
            return json_decode($result, true);
        }

        return $result;
    }

    /**
     * Set an EAV attribute value
     */
    public function setEavAttribute($key, $value)
    {
        // Get attribute definition
        $attrDef = DB::table('company_attribute_definitions')
            ->where('company_type_id', $this->company_type_id)
            ->where('attribute_key', $key)
            ->first();

        if (!$attrDef) {
            return false;
        }

        // Prepare data
        $data = [
            'company_id' => $this->id,
            'attribute_definition_id' => $attrDef->id,
            'updated_at' => now()
        ];

        // Store value in appropriate column based on data type
        switch ($attrDef->data_type) {
            case 'string':
            case 'text':
                $data['value_text'] = $value;
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

        // Insert or update
        DB::table('company_attribute_values')
            ->updateOrInsert(
                [
                    'company_id' => $this->id,
                    'attribute_definition_id' => $attrDef->id
                ],
                $data
            );

        return true;
    }

    /**
     * Get company with all its EAV attributes
     */
    public function toArrayWithAttributes()
    {
        $data = $this->toArray();
        $attributes = $this->getAttributesData();
        
        // Merge EAV attributes with model attributes
        foreach ($attributes as $key => $value) {
            // Parse JSON values
            if ($this->isJson($value)) {
                $data[$key] = json_decode($value, true);
            } else {
                $data[$key] = $value;
            }
        }

        // Add company type info
        if ($this->companyType) {
            $data['company_type'] = $this->companyType->toArray();
        }

        return $data;
    }

    /**
     * Get company entities with their attributes
     */
    public function getEntitiesWithAttributes()
    {
        $entities = [];

        // Get all entity types for this company type
        $entityTypes = DB::table('company_entity_types')
            ->where('parent_company_type_id', $this->company_type_id)
            ->get();

        foreach ($entityTypes as $entityType) {
            $entityData = DB::table('company_entities')
                ->where('company_id', $this->id)
                ->where('entity_type_id', $entityType->id)
                ->orderBy('display_order')
                ->orderBy('id')
                ->get();

            $items = [];
            foreach ($entityData as $entity) {
                // Get attributes for this entity
                $attributes = DB::table('v_entity_attributes')
                    ->where('entity_id', $entity->id)
                    ->get()
                    ->mapWithKeys(function ($item) {
                        $value = $item->attribute_value;
                        // Parse JSON values
                        if ($this->isJson($value)) {
                            $value = json_decode($value, true);
                        }
                        return [$item->attribute_key => $value];
                    });

                $entityArray = (array) $entity;

                // Parse entity_name if it's JSON (for translations)
                if (isset($entityArray['entity_name']) && $this->isJson($entityArray['entity_name'])) {
                    $entityArray['entity_name'] = json_decode($entityArray['entity_name'], true);
                }

                // Add the name field for backward compatibility
                $entityArray['name'] = $entityArray['entity_name'];

                // Add type information
                $entityArray['entity_type'] = $entityType->entity_name;

                foreach ($attributes as $key => $value) {
                    $entityArray[$key] = $value;
                }

                $items[] = $entityArray;
            }

            if (!empty($items)) {
                $entities[$entityType->entity_name] = $items;
            }
        }

        return $entities;
    }

    /**
     * Check if a string is JSON
     */
    private function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Scope for active companies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for companies by type
     */
    public function scopeOfType($query, $typeName)
    {
        return $query->whereHas('companyType', function ($q) use ($typeName) {
            $q->where('type_name', $typeName);
        });
    }
}