<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSender extends Model
{
    protected $fillable = [
        'user_id',
        'sender_name',
        'is_active',
        'approved_at',
        'approved_by',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'approved_at' => 'datetime',
    ];

    // Default sender name available to all users
    public const DEFAULT_SENDER = 'Alert.az';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get all available senders for a user (including default)
     */
    public static function getAvailableSenders(int $userId): array
    {
        $userSenders = self::where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('sender_name')
            ->toArray();

        // Always include the default sender at the beginning
        return array_unique(array_merge([self::DEFAULT_SENDER], $userSenders));
    }

    /**
     * Check if a sender is allowed for a user
     */
    public static function isAllowedForUser(int $userId, string $senderName): bool
    {
        // Default sender is always allowed
        if ($senderName === self::DEFAULT_SENDER) {
            return true;
        }

        return self::where('user_id', $userId)
            ->where('sender_name', $senderName)
            ->where('is_active', true)
            ->exists();
    }
}
