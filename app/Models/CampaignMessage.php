<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMessage extends Model
{
    protected $fillable = [
        'campaign_id',
        'contact_id',
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
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // Relationships
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    // Helper methods
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
}
