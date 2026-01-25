<?php

namespace App\Traits;

use App\Models\Client;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait for models that belong to a client/tenant
 */
trait BelongsToClient
{
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Boot the trait to automatically set client_id on creation
     */
    public static function bootBelongsToClient(): void
    {
        static::creating(function ($model) {
            if (empty($model->client_id) && request()->has('client_id')) {
                $model->client_id = request()->input('client_id');
            }
        });
    }
}
