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

    public function serviceTypes(): HasMany
    {
        return $this->hasMany(ServiceType::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function cooldowns(): HasMany
    {
        return $this->hasMany(Cooldown::class);
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

    public function getSetting(string $key, $default = null)
    {
        $settings = $this->settings ?? [];
        return $settings[$key] ?? $default;
    }

    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;
        $this->save();
    }

    /**
     * Get service type by key
     */
    public function getServiceType(string $key): ?ServiceType
    {
        return $this->serviceTypes()->where('key', $key)->first();
    }

    /**
     * Get or create service type
     */
    public function getOrCreateServiceType(string $key, array $data = []): ServiceType
    {
        return $this->serviceTypes()->firstOrCreate(
            ['key' => $key],
            array_merge([
                'label' => ['en' => ucfirst($key)],
                'fields' => [],
            ], $data)
        );
    }

    /**
     * Get stats for this client
     */
    public function getStats(): array
    {
        return [
            'customers_count' => $this->customers()->count(),
            'services_count' => $this->services()->count(),
            'service_types_count' => $this->serviceTypes()->count(),
            'campaigns_count' => $this->campaigns()->count(),
            'active_campaigns' => $this->campaigns()->active()->count(),
            'messages_sent' => $this->messages()->sent()->count(),
        ];
    }
}
