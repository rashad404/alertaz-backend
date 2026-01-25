<?php

namespace App\Models;

use App\Traits\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceType extends Model
{
    use BelongsToClient;

    protected $fillable = [
        'client_id',
        'key',
        'label',
        'icon',
        'user_link_field',
        'fields',
        'display_order',
    ];

    protected $casts = [
        'label' => 'array',
        'fields' => 'array',
    ];

    /**
     * Get services of this type
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Get campaigns targeting this service type
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Get label for a specific locale
     */
    public function getLabelForLocale(string $locale = 'en'): string
    {
        $labels = $this->label ?? [];
        return $labels[$locale] ?? $labels['en'] ?? $this->key;
    }

    /**
     * Get field definition by name
     */
    public function getField(string $name): ?array
    {
        $fields = $this->fields ?? [];
        return $fields[$name] ?? null;
    }

    /**
     * Get all field names
     */
    public function getFieldNames(): array
    {
        return array_keys($this->fields ?? []);
    }

    /**
     * Validate data against field definitions
     */
    public function validateData(array $data): array
    {
        $errors = [];
        $fields = $this->fields ?? [];

        foreach ($fields as $name => $definition) {
            $required = $definition['required'] ?? false;
            $type = $definition['type'] ?? 'string';

            if ($required && empty($data[$name])) {
                $errors[$name] = "The {$name} field is required.";
                continue;
            }

            if (isset($data[$name])) {
                $value = $data[$name];
                switch ($type) {
                    case 'date':
                        if (!strtotime($value)) {
                            $errors[$name] = "The {$name} field must be a valid date.";
                        }
                        break;
                    case 'number':
                    case 'integer':
                        if (!is_numeric($value)) {
                            $errors[$name] = "The {$name} field must be a number.";
                        }
                        break;
                    case 'enum':
                        $options = $definition['options'] ?? [];
                        if (!in_array($value, $options)) {
                            $errors[$name] = "The {$name} field must be one of: " . implode(', ', $options);
                        }
                        break;
                }
            }
        }

        return $errors;
    }
}
