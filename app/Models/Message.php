<?php

namespace App\Models;

use App\Traits\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Message extends Model
{
    use BelongsToClient;

    const CHANNEL_SMS = 'sms';
    const CHANNEL_EMAIL = 'email';

    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED = 'failed';
    const STATUS_BOUNCED = 'bounced';
    const STATUS_OPENED = 'opened';
    const STATUS_CLICKED = 'clicked';

    protected $fillable = [
        'client_id',
        'campaign_id',
        'customer_id',
        'service_id',
        'channel',
        'recipient',
        'content',
        'subject',
        'sender',
        'status',
        'is_test',
        'source',
        'provider_message_id',
        'error_message',
        'error_code',
        'cost',
        'segments',
        'sent_at',
        'delivered_at',
        'opened_at',
        'clicked_at',
    ];

    protected $casts = [
        'cost' => 'decimal:4',
        'segments' => 'integer',
        'is_test' => 'boolean',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
    ];

    /**
     * Get the campaign this message belongs to
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the customer this message was sent to
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the service this message is about
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Scope for SMS messages
     */
    public function scopeSms(Builder $query): Builder
    {
        return $query->where('channel', self::CHANNEL_SMS);
    }

    /**
     * Scope for email messages
     */
    public function scopeEmail(Builder $query): Builder
    {
        return $query->where('channel', self::CHANNEL_EMAIL);
    }

    /**
     * Scope for messages by status
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for pending messages
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for sent messages
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_SENT, self::STATUS_DELIVERED, self::STATUS_OPENED, self::STATUS_CLICKED]);
    }

    /**
     * Scope for failed messages
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_FAILED, self::STATUS_BOUNCED]);
    }

    /**
     * Mark message as sent
     */
    public function markAsSent(?string $providerMessageId = null): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'provider_message_id' => $providerMessageId,
        ]);
    }

    /**
     * Mark message as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark message as failed
     */
    public function markAsFailed(?string $errorMessage = null, ?int $errorCode = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'error_code' => $errorCode,
        ]);
    }

    /**
     * Mark message as opened (email only)
     */
    public function markAsOpened(): void
    {
        if ($this->channel === self::CHANNEL_EMAIL) {
            $this->update([
                'status' => self::STATUS_OPENED,
                'opened_at' => now(),
            ]);
        }
    }

    /**
     * Mark message as clicked (email only)
     */
    public function markAsClicked(): void
    {
        if ($this->channel === self::CHANNEL_EMAIL) {
            $this->update([
                'status' => self::STATUS_CLICKED,
                'clicked_at' => now(),
            ]);
        }
    }

    /**
     * Check if message is SMS
     */
    public function isSms(): bool
    {
        return $this->channel === self::CHANNEL_SMS;
    }

    /**
     * Check if message is email
     */
    public function isEmail(): bool
    {
        return $this->channel === self::CHANNEL_EMAIL;
    }

    /**
     * Check if message was successfully sent
     */
    public function wasSent(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_DELIVERED, self::STATUS_OPENED, self::STATUS_CLICKED]);
    }

    /**
     * Check if message failed
     */
    public function hasFailed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_BOUNCED]);
    }

    /**
     * Create SMS message
     */
    public static function createSms(array $data): self
    {
        return self::create(array_merge($data, ['channel' => self::CHANNEL_SMS]));
    }

    /**
     * Create email message
     */
    public static function createEmail(array $data): self
    {
        return self::create(array_merge($data, ['channel' => self::CHANNEL_EMAIL]));
    }
}
