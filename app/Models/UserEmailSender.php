<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserEmailSender extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'name',
        'is_verified',
        'is_active',
        'is_default',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'verified_at' => 'datetime',
    ];

    // Default email sender
    public const DEFAULT_EMAIL = 'noreply@alert.az';
    public const DEFAULT_NAME = 'Alert.az';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('is_default', true);
        });
    }

    /**
     * Get all available email senders for a user (including defaults)
     * Returns array of ['email' => '...', 'name' => '...', 'label' => '...']
     */
    public static function getAvailableSenders(int $userId): array
    {
        $senders = self::where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhere('is_default', true);
            })
            ->where('is_active', true)
            ->where('is_verified', true)
            ->get()
            ->map(function ($sender) {
                return [
                    'email' => $sender->email,
                    'name' => $sender->name,
                    'label' => "{$sender->name} <{$sender->email}>",
                ];
            })
            ->toArray();

        // Ensure default is always available
        if (empty($senders)) {
            $senders = [
                [
                    'email' => self::DEFAULT_EMAIL,
                    'name' => self::DEFAULT_NAME,
                    'label' => self::DEFAULT_NAME . ' <' . self::DEFAULT_EMAIL . '>',
                ]
            ];
        }

        return $senders;
    }

    /**
     * Get default email sender
     */
    public static function getDefault(): array
    {
        return [
            'email' => self::DEFAULT_EMAIL,
            'name' => self::DEFAULT_NAME,
            'label' => self::DEFAULT_NAME . ' <' . self::DEFAULT_EMAIL . '>',
        ];
    }

    /**
     * Check if an email sender is allowed for a user
     */
    public static function isAllowedForUser(int $userId, string $email): bool
    {
        // Default sender is always allowed
        if ($email === self::DEFAULT_EMAIL) {
            return true;
        }

        return self::where('user_id', $userId)
            ->where('email', $email)
            ->where('is_active', true)
            ->where('is_verified', true)
            ->exists();
    }

    /**
     * Get sender details by email
     */
    public static function getByEmail(string $email): ?array
    {
        $sender = self::where('email', $email)
            ->where('is_active', true)
            ->where('is_verified', true)
            ->first();

        if ($sender) {
            return [
                'email' => $sender->email,
                'name' => $sender->name,
            ];
        }

        // Return default if email matches
        if ($email === self::DEFAULT_EMAIL) {
            return [
                'email' => self::DEFAULT_EMAIL,
                'name' => self::DEFAULT_NAME,
            ];
        }

        return null;
    }
}
