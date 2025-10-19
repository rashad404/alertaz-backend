<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyEntityType extends Model
{
    protected $fillable = [
        'entity_name',
        'parent_company_type_id',
        'description',
        'display_order',
    ];

    public function companyType(): BelongsTo
    {
        return $this->belongsTo(CompanyType::class, 'parent_company_type_id');
    }
}