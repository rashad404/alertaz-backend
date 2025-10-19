<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyAttributeDefinition extends Model
{
    protected $fillable = [
        'entity_type_id',
        'company_type_id',
        'attribute_group_id',
        'attribute_name',
        'attribute_key',
        'data_type',
        'is_required',
        'is_translatable',
        'validation_rules',
        'display_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_translatable' => 'boolean',
        'validation_rules' => 'json',
    ];
}