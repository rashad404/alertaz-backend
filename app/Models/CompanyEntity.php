<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class CompanyEntity extends Model
{
    use HasTranslations;

    protected $fillable = [
        'company_id',
        'entity_type_id',
        'entity_name',
        'entity_code',
        'is_active',
        'display_order',
    ];

    public array $translatable = [
        'entity_name',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function entityType(): BelongsTo
    {
        return $this->belongsTo(CompanyEntityType::class, 'entity_type_id');
    }
}