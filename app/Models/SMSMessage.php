<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SMSMessage extends Model
{
    protected $table = 'sms_messages';

    protected $fillable = [
        'user_id',
        'phone',
        'message',
        'sender',
        'cost',
        'status',
        'provider_transaction_id',
        'delivery_status_code',
        'error_message',
        'sent_at',
        'delivered_at',
        'ip_address',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsSent(string $transactionId): void
    {
        $this->update([
            'status' => 'sent',
            'provider_transaction_id' => $transactionId,
            'sent_at' => now(),
        ]);
    }

    public function markAsDelivered(int $statusCode): void
    {
        $this->update([
            'status' => 'delivered',
            'delivery_status_code' => $statusCode,
            'delivered_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage, ?int $statusCode = null): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'delivery_status_code' => $statusCode,
        ]);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
