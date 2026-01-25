<?php

namespace App\Models;

use App\Traits\BelongsToClient;
use App\Traits\Filterable;
use App\Helpers\EmailValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Customer extends Model
{
    use BelongsToClient, Filterable;

    protected $fillable = [
        'client_id',
        'external_id',
        'phone',
        'email',
        'name',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Direct columns (not in JSON data)
     */
    protected function getDirectColumns(): array
    {
        return ['id', 'client_id', 'external_id', 'phone', 'email', 'name', 'created_at', 'updated_at'];
    }

    /**
     * Get services belonging to this customer
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Get messages sent to this customer
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
     * Check if customer has a phone number
     */
    public function hasPhone(): bool
    {
        return !empty($this->phone);
    }

    /**
     * Check if customer has an email address
     */
    public function hasEmail(): bool
    {
        return !empty($this->email) && EmailValidator::isValid($this->email);
    }

    /**
     * Check if customer can receive SMS
     */
    public function canReceiveSms(): bool
    {
        return $this->hasPhone();
    }

    /**
     * Check if customer can receive email
     */
    public function canReceiveEmail(): bool
    {
        return $this->hasEmail();
    }

    /**
     * Get phone for messaging
     */
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    /**
     * Get email for messaging
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Get email for validation
     */
    public function getEmailForValidation(): ?string
    {
        return $this->email;
    }

    /**
     * Get display name
     */
    public function getDisplayName(): string
    {
        return $this->name ?? $this->email ?? $this->phone ?? "Customer #{$this->id}";
    }

    /**
     * Get all available variables for templating
     */
    public function getTemplateVariables(): array
    {
        $variables = [
            'customer_id' => $this->id,
            'customer_name' => $this->name ?? '',
            'customer_email' => $this->email ?? '',
            'customer_phone' => $this->phone ?? '',
            'name' => $this->name ?? '',
            'email' => $this->email ?? '',
            'phone' => $this->phone ?? '',
        ];

        // Add all data fields
        foreach ($this->data ?? [] as $key => $value) {
            $variables[$key] = $value;
        }

        return $variables;
    }

    /**
     * Find by identifier (phone, email, or external_id)
     */
    public function scopeFindByIdentifier(Builder $query, int $clientId, string $identifier): Builder
    {
        return $query->forClient($clientId)
            ->where(function ($q) use ($identifier) {
                $q->where('phone', $identifier)
                  ->orWhere('email', $identifier)
                  ->orWhere('external_id', $identifier);
            });
    }

    /**
     * Find or create customer by data
     */
    public static function findOrCreateByData(int $clientId, array $data): self
    {
        $query = self::forClient($clientId);

        // Try to find by identifiers in order of precedence
        if (!empty($data['external_id'])) {
            $existing = (clone $query)->where('external_id', $data['external_id'])->first();
            if ($existing) return $existing;
        }

        if (!empty($data['phone'])) {
            $existing = (clone $query)->where('phone', $data['phone'])->first();
            if ($existing) return $existing;
        }

        if (!empty($data['email'])) {
            $existing = (clone $query)->where('email', $data['email'])->first();
            if ($existing) return $existing;
        }

        // Create new
        return self::create([
            'client_id' => $clientId,
            'external_id' => $data['external_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'name' => $data['name'] ?? null,
            'data' => $data['data'] ?? null,
        ]);
    }

    /**
     * Create a sample instance for testing (not saved to DB)
     */
    public static function createSampleInstance(int $clientId, ?string $phone = null, ?string $email = null): self
    {
        $customer = new self([
            'client_id' => $clientId,
            'phone' => $phone ?? '994501234567',
            'email' => $email ?? 'sample@example.com',
            'name' => 'Sample Customer',
            'data' => [
                'company' => 'Sample Company',
            ],
        ]);
        $customer->id = 0;

        return $customer;
    }
}
