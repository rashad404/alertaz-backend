<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Client extends Model
{
    protected $fillable = [
        'name',
        'api_token',
        'user_id',
        'status',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    protected $hidden = [
        'api_token',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attributeSchemas(): HasMany
    {
        return $this->hasMany(ClientAttributeSchema::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function savedSegments(): HasMany
    {
        return $this->hasMany(SavedSegment::class);
    }

    // Helper methods
    public static function generateApiToken(): string
    {
        return hash('sha256', Str::random(60));
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
