<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailMessage extends Model
{
    protected $table = 'email_messages';

    protected $fillable = [
        'user_id',
        'source',
        'client_id',
        'campaign_id',
        'contact_id',
        'to_email',
        'to_name',
        'from_email',
        'from_name',
        'subject',
        'body_html',
        'body_text',
        'cost',
        'status',
        'is_test',
        'provider_message_id',
        'error_message',
        'error_code',
        'retry_count',
        'last_retry_at',
        'failure_reason',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
        'ip_address',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'is_test' => 'boolean',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
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

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function markAsSent(?string $messageId = null): void
    {
        $this->update([
            'status' => 'sent',
            'provider_message_id' => $messageId,
            'sent_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage, ?string $errorCode = null, ?string $failureReason = null): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
            'failure_reason' => $failureReason,
        ]);
    }

    public function markAsBounced(string $reason): void
    {
        $this->update([
            'status' => 'bounced',
            'error_message' => $reason,
            'failure_reason' => 'bounce',
        ]);
    }

    public function markAsOpened(): void
    {
        if (!$this->opened_at) {
            $this->update(['opened_at' => now()]);
        }
    }

    public function markAsClicked(): void
    {
        if (!$this->clicked_at) {
            $this->update(['clicked_at' => now()]);
        }
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

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeVerification($query)
    {
        return $query->where('source', 'verification');
    }
}
