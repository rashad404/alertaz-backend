<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    protected $table = 'sms_messages';

    protected $fillable = [
        'user_id',
        'source',
        'client_id',
        'campaign_id',
        'contact_id',
        'phone',
        'message',
        'sender',
        'cost',
        'status',
        'is_test',
        'provider_transaction_id',
        'delivery_status_code',
        'error_message',
        'error_code',
        'retry_count',
        'last_retry_at',
        'failure_reason',
        'sent_at',
        'delivered_at',
        'ip_address',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'is_test' => 'boolean',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'last_retry_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
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

    public function markAsFailed(string $errorMessage, ?int $statusCode = null, ?int $errorCode = null, ?string $failureReason = null): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'delivery_status_code' => $statusCode,
            'error_code' => $errorCode,
            'failure_reason' => $failureReason,
        ]);
    }

    public function incrementRetryCount(): void
    {
        $this->update([
            'retry_count' => $this->retry_count + 1,
            'last_retry_at' => now(),
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

    public function scopeFromApi($query)
    {
        return $query->where('source', 'api');
    }

    public function scopeFromCampaign($query)
    {
        return $query->where('source', 'campaign');
    }

    public function scopeForCampaign($query, int $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }
}
