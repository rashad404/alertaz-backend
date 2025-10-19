<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyType extends Model
{
    protected $fillable = [
        'type_name',
        'description',
        'parent_id',
        'slug',
        'display_order',
        'is_active',
    ];

    /**
     * Get companies of this type
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'company_type_id');
    }

    /**
     * Get entity types for this company type
     */
    public function entityTypes(): HasMany
    {
        return $this->hasMany(CompanyEntityType::class, 'parent_company_type_id');
    }

    /**
     * Get attribute definitions for this company type
     */
    public function attributeDefinitions(): HasMany
    {
        return $this->hasMany(CompanyAttributeDefinition::class, 'company_type_id');
    }

    /**
     * Get the parent company type
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(CompanyType::class, 'parent_id');
    }

    /**
     * Get the child company types (subcategories)
     */
    public function children(): HasMany
    {
        return $this->hasMany(CompanyType::class, 'parent_id')->orderBy('display_order');
    }

    /**
     * Check if this is a parent category
     */
    public function isParent(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Get all companies including subcategories
     */
    public function allCompanies()
    {
        if ($this->isParent()) {
            $childIds = $this->children()->pluck('id')->toArray();
            $allIds = array_merge([$this->id], $childIds);
            return Company::whereIn('company_type_id', $allIds);
        }
        return $this->companies();
    }
}