<?php

namespace App\Models;

use App\Traits\BelongsToClient;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Service extends Model
{
    use BelongsToClient, Filterable;

    protected $fillable = [
        'client_id',
        'service_type_id',
        'customer_id',
        'external_id',
        'name',
        'expiry_at',
        'status',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
        'expiry_at' => 'date',
    ];

    /**
     * Direct columns (not in JSON data)
     */
    protected function getDirectColumns(): array
    {
        return ['id', 'client_id', 'service_type_id', 'customer_id', 'external_id', 'name', 'expiry_at', 'status', 'created_at', 'updated_at'];
    }

    /**
     * Get the service type
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    /**
     * Get the customer who owns this service
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get messages related to this service
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get a data attribute by key
     */
    public function getData(string $key, $default = null)
    {
        $data = $this->data ?? [];
        return $data[$key] ?? $default;
    }

    /**
     * Set a data attribute
     */
    public function setData(string $key, $value): void
    {
        $data = $this->data ?? [];
        $data[$key] = $value;
        $this->data = $data;
    }

    /**
     * Check if service is expired
     */
    public function isExpired(): bool
    {
        if (!$this->expiry_at) {
            return false;
        }
        return $this->expiry_at->isPast();
    }

    /**
     * Check if service expires within N days
     */
    public function expiresWithinDays(int $days): bool
    {
        if (!$this->expiry_at) {
            return false;
        }
        return $this->expiry_at->isBetween(Carbon::now(), Carbon::now()->addDays($days));
    }

    /**
     * Get days until expiry
     */
    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->expiry_at) {
            return null;
        }
        return Carbon::now()->startOfDay()->diffInDays($this->expiry_at->startOfDay(), false);
    }

    /**
     * Check if service is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Scope for active services
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for services expiring within N days
     */
    public function scopeExpiringWithinDays(Builder $query, int $days): Builder
    {
        return $query->whereNotNull('expiry_at')
            ->whereDate('expiry_at', '>=', Carbon::now())
            ->whereDate('expiry_at', '<=', Carbon::now()->addDays($days));
    }

    /**
     * Scope for expired services
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiry_at')
            ->whereDate('expiry_at', '<', Carbon::now());
    }

    /**
     * Scope to exclude services in cooldown for a campaign
     */
    public function scopeNotInCooldown(Builder $query, int $campaignId, int $cooldownDays): Builder
    {
        return $query->whereNotExists(function ($subquery) use ($campaignId, $cooldownDays) {
            $subquery->select(\DB::raw(1))
                ->from('cooldowns')
                ->whereColumn('cooldowns.target_id', 'services.id')
                ->where('cooldowns.target_type', 'service')
                ->where('cooldowns.campaign_id', $campaignId)
                ->where('cooldowns.sent_at', '>=', Carbon::now()->subDays($cooldownDays));
        });
    }

    /**
     * Get all available variables for templating
     */
    public function getTemplateVariables(): array
    {
        $variables = [
            'service_id' => $this->id,
            'service_name' => $this->name,
            'name' => $this->name,
            'expiry_at' => $this->expiry_at?->format('d.m.Y') ?? '',
            'expiry_date' => $this->expiry_at?->format('d.m.Y') ?? '',
            'status' => $this->status ?? '',
            'days_until_expiry' => $this->getDaysUntilExpiry() ?? '',
        ];

        // Add customer variables if available
        if ($this->customer) {
            $variables['customer_name'] = $this->customer->name ?? '';
            $variables['customer_email'] = $this->customer->email ?? '';
            $variables['customer_phone'] = $this->customer->phone ?? '';
            $variables['user_name'] = $this->customer->name ?? '';
            $variables['user_email'] = $this->customer->email ?? '';
            $variables['user_phone'] = $this->customer->phone ?? '';
        }

        // Add all data fields
        foreach ($this->data ?? [] as $key => $value) {
            $variables[$key] = $value;
        }

        return $variables;
    }

    /**
     * Link service to customer by field value
     */
    public function linkToCustomerByField(string $field, $value): ?Customer
    {
        if (empty($value)) {
            return null;
        }

        $customer = Customer::forClient($this->client_id)
            ->where($field, $value)
            ->first();

        if ($customer) {
            $this->customer_id = $customer->id;
            $this->save();
        }

        return $customer;
    }

    /**
     * Find or create service by data
     */
    public static function findOrCreateByData(int $clientId, int $serviceTypeId, array $data): self
    {
        // Try to find by external_id first
        if (!empty($data['external_id'])) {
            $existing = self::forClient($clientId)
                ->where('service_type_id', $serviceTypeId)
                ->where('external_id', $data['external_id'])
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // Create new
        return self::create([
            'client_id' => $clientId,
            'service_type_id' => $serviceTypeId,
            'external_id' => $data['external_id'] ?? null,
            'name' => $data['name'],
            'expiry_at' => $data['expiry_at'] ?? null,
            'status' => $data['status'] ?? 'active',
            'data' => $data['data'] ?? null,
        ]);
    }

    /**
     * Check if service can receive SMS (via linked customer)
     */
    public function canReceiveSms(): bool
    {
        return $this->customer && $this->customer->hasPhone();
    }

    /**
     * Check if service can receive email (via linked customer)
     */
    public function canReceiveEmail(): bool
    {
        return $this->customer && $this->customer->hasEmail();
    }

    /**
     * Get email for validation (from linked customer)
     */
    public function getEmailForValidation(): ?string
    {
        return $this->customer?->email;
    }

    /**
     * Get phone for messaging (from linked customer)
     */
    public function getPhone(): ?string
    {
        return $this->customer?->phone;
    }

    /**
     * Get email for messaging (from linked customer)
     */
    public function getEmail(): ?string
    {
        return $this->customer?->email;
    }
}
