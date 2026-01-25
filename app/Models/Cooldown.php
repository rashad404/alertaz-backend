<?php

namespace App\Models;

use App\Traits\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Cooldown extends Model
{
    use BelongsToClient;

    public $timestamps = false;

    const TARGET_CUSTOMER = 'customer';
    const TARGET_SERVICE = 'service';

    protected $fillable = [
        'client_id',
        'campaign_id',
        'target_type',
        'target_id',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * Get the campaign
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the target (customer or service) - polymorphic-like
     */
    public function target()
    {
        if ($this->target_type === self::TARGET_CUSTOMER) {
            return Customer::find($this->target_id);
        }
        return Service::find($this->target_id);
    }

    /**
     * Scope for customer cooldowns
     */
    public function scopeForCustomers(Builder $query): Builder
    {
        return $query->where('target_type', self::TARGET_CUSTOMER);
    }

    /**
     * Scope for service cooldowns
     */
    public function scopeForServices(Builder $query): Builder
    {
        return $query->where('target_type', self::TARGET_SERVICE);
    }

    /**
     * Scope for active cooldowns (not expired)
     */
    public function scopeActive(Builder $query, int $cooldownDays): Builder
    {
        return $query->where('sent_at', '>=', Carbon::now()->subDays($cooldownDays));
    }

    /**
     * Check if a target is in cooldown
     */
    public static function isInCooldown(int $campaignId, string $targetType, int $targetId, int $cooldownDays): bool
    {
        return self::where('campaign_id', $campaignId)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('sent_at', '>=', Carbon::now()->subDays($cooldownDays))
            ->exists();
    }

    /**
     * Record a cooldown for a customer
     */
    public static function recordForCustomer(int $clientId, int $campaignId, int $customerId): self
    {
        return self::updateOrCreate(
            [
                'campaign_id' => $campaignId,
                'target_type' => self::TARGET_CUSTOMER,
                'target_id' => $customerId,
            ],
            [
                'client_id' => $clientId,
                'sent_at' => now(),
            ]
        );
    }

    /**
     * Record a cooldown for a service
     */
    public static function recordForService(int $clientId, int $campaignId, int $serviceId): self
    {
        return self::updateOrCreate(
            [
                'campaign_id' => $campaignId,
                'target_type' => self::TARGET_SERVICE,
                'target_id' => $serviceId,
            ],
            [
                'client_id' => $clientId,
                'sent_at' => now(),
            ]
        );
    }

    /**
     * Clean up old cooldowns
     */
    public static function cleanup(int $olderThanDays = 90): int
    {
        return self::where('sent_at', '<', Carbon::now()->subDays($olderThanDays))->delete();
    }
}
