<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAttributeSchema extends Model
{
    protected $fillable = [
        'client_id',
        'attribute_key',
        'attribute_type',
        'label',
        'options',
        'item_type',
        'properties',
        'required',
        'metadata',
    ];

    protected $casts = [
        'options' => 'array',
        'properties' => 'array',
        'metadata' => 'array',
        'required' => 'boolean',
    ];

    // Relationships
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // Helper methods
    public function getConditionsForType(): array
    {
        return match($this->attribute_type) {
            'string' => [
                'equals', 'not_equals', 'contains', 'not_contains',
                'starts_with', 'ends_with', 'is_set', 'is_not_set'
            ],
            'number', 'integer' => [
                'equals', 'not_equals', 'greater_than', 'less_than',
                'greater_than_or_equal', 'less_than_or_equal', 'between'
            ],
            'date' => [
                'expires_within', 'expired_since', 'equals_date',
                'before', 'after', 'between', 'is_set', 'is_not_set'
            ],
            'boolean' => [
                'is_true', 'is_false', 'is_set', 'is_not_set'
            ],
            'enum' => [
                'equals', 'not_equals', 'in', 'not_in'
            ],
            'array' => [
                'count', 'any', 'all', 'none', 'exists', 'is_empty'
            ],
            default => []
        };
    }
}
