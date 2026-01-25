<?php

namespace App\Models;

use App\Traits\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Campaign extends Model
{
    use BelongsToClient;

    // Target types
    const TARGET_CUSTOMER = 'customer';
    const TARGET_SERVICE = 'service';

    // Campaign types
    const TYPE_ONE_TIME = 'one_time';
    const TYPE_AUTOMATED = 'automated';

    // Channel types
    const CHANNEL_SMS = 'sms';
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_BOTH = 'both';

    // Statuses
    const STATUS_DRAFT = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_SENDING = 'sending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FAILED = 'failed';
    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'client_id',
        'name',
        'target_type',
        'service_type_id',
        'channel',
        'sender',
        'email_sender',
        'email_display_name',
        'message_template',
        'email_subject',
        'email_body',
        'filter',
        'status',
        'campaign_type',
        'scheduled_at',
        'check_interval_minutes',
        'cooldown_days',
        'run_start_hour',
        'run_end_hour',
        'next_run_at',
        'last_run_at',
        'ends_at',
        'run_count',
        'started_at',
        'completed_at',
        'target_count',
        'sent_count',
        'delivered_count',
        'failed_count',
        'total_cost',
        'email_sent_count',
        'email_delivered_count',
        'email_failed_count',
        'email_total_cost',
        'balance_warning_sent',
        'pause_reason',
        'created_by',
        'is_test',
    ];

    protected $appends = [
        'segment_filter',
        'type',
        'email_subject_template',
        'email_body_template',
    ];

    protected $with = [
        'serviceType:id,key,label',
    ];

    protected $casts = [
        'filter' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'ends_at' => 'datetime',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'is_test' => 'boolean',
        'balance_warning_sent' => 'boolean',
        'total_cost' => 'decimal:2',
        'email_total_cost' => 'decimal:2',
    ];

    // Accessors for backwards compatibility

    /**
     * Get segment_filter attribute (alias for filter column)
     */
    public function getSegmentFilterAttribute(): ?array
    {
        return $this->filter;
    }

    /**
     * Set segment_filter attribute (alias for filter column)
     * Note: We set 'filter' directly and let the 'array' cast handle encoding
     */
    public function setSegmentFilterAttribute($value): void
    {
        $this->filter = $value;
    }

    /**
     * Get email_subject_template attribute (alias for email_subject column)
     */
    public function getEmailSubjectTemplateAttribute(): ?string
    {
        return $this->email_subject;
    }

    /**
     * Get email_body_template attribute (alias for email_body column)
     */
    public function getEmailBodyTemplateAttribute(): ?string
    {
        return $this->email_body;
    }

    /**
     * Get type attribute (alias for campaign_type column)
     */
    public function getTypeAttribute(): ?string
    {
        return $this->campaign_type;
    }

    // Relationships

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function cooldowns(): HasMany
    {
        return $this->hasMany(Cooldown::class);
    }

    // Target type helpers

    public function targetsCustomers(): bool
    {
        return $this->target_type === self::TARGET_CUSTOMER;
    }

    public function targetsServices(): bool
    {
        return $this->target_type === self::TARGET_SERVICE;
    }

    // Channel helpers

    public function requiresPhone(): bool
    {
        return in_array($this->channel, [self::CHANNEL_SMS, self::CHANNEL_BOTH]);
    }

    public function requiresEmail(): bool
    {
        return in_array($this->channel, [self::CHANNEL_EMAIL, self::CHANNEL_BOTH]);
    }

    public function isSmsOnly(): bool
    {
        return $this->channel === self::CHANNEL_SMS;
    }

    public function isEmailOnly(): bool
    {
        return $this->channel === self::CHANNEL_EMAIL;
    }

    public function usesBothChannels(): bool
    {
        return $this->channel === self::CHANNEL_BOTH;
    }

    // Campaign type helpers

    public function isAutomated(): bool
    {
        return $this->campaign_type === self::TYPE_AUTOMATED;
    }

    public function isOneTime(): bool
    {
        return $this->campaign_type === self::TYPE_ONE_TIME;
    }

    // Status helpers

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function hasEnded(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    // Scopes

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePaused(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAUSED);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeAutomated(Builder $query): Builder
    {
        return $query->where('campaign_type', self::TYPE_AUTOMATED);
    }

    public function scopeOneTime(Builder $query): Builder
    {
        return $query->where('campaign_type', self::TYPE_ONE_TIME);
    }

    public function scopeDueToRun(Builder $query): Builder
    {
        return $query->where('campaign_type', self::TYPE_AUTOMATED)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                  ->orWhere('next_run_at', '<=', now());
            });
    }

    // Status transitions

    public function markAsScheduled(): void
    {
        $this->update(['status' => self::STATUS_SCHEDULED]);
    }

    public function markAsSending(): void
    {
        $this->update([
            'status' => self::STATUS_SENDING,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => self::STATUS_FAILED]);
    }

    public function activate(): void
    {
        $nextRunAt = $this->calculateNextRunTime(now());

        $this->update([
            'status' => self::STATUS_ACTIVE,
            'next_run_at' => $nextRunAt,
            'balance_warning_sent' => false,
            'pause_reason' => null,
        ]);
    }

    public function pause(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_PAUSED,
            'next_run_at' => null,
            'pause_reason' => $reason,
        ]);
    }

    public function scheduleNextRun(): void
    {
        if ($this->check_interval_minutes) {
            $baseNextRun = now()->addMinutes($this->check_interval_minutes);
            $nextRun = $this->calculateNextRunTime($baseNextRun);

            $this->update([
                'last_run_at' => now(),
                'next_run_at' => $nextRun,
                'run_count' => $this->run_count + 1,
            ]);
        }
    }

    // Run window helpers

    public function isWithinRunWindow(int $hour): bool
    {
        if ($this->run_start_hour === null || $this->run_end_hour === null) {
            return true;
        }
        return $hour >= $this->run_start_hour && $hour < $this->run_end_hour;
    }

    public function calculateNextRunTime(?Carbon $baseTime = null): Carbon
    {
        $nextRun = $baseTime ?? now();

        if ($this->run_start_hour === null || $this->run_end_hour === null) {
            return $nextRun;
        }

        if ($this->isWithinRunWindow($nextRun->hour)) {
            return $nextRun;
        }

        if ($nextRun->hour < $this->run_start_hour) {
            return $nextRun->copy()->setTime($this->run_start_hour, 0, 0);
        }

        return $nextRun->copy()->addDay()->setTime($this->run_start_hour, 0, 0);
    }

    // Stats helpers

    public function incrementSentCount(): void
    {
        $this->increment('sent_count');
    }

    public function incrementDeliveredCount(): void
    {
        $this->increment('delivered_count');
    }

    public function incrementFailedCount(): void
    {
        $this->increment('failed_count');
    }

    public function incrementEmailSentCount(): void
    {
        $this->increment('email_sent_count');
    }

    public function incrementEmailDeliveredCount(): void
    {
        $this->increment('email_delivered_count');
    }

    public function incrementEmailFailedCount(): void
    {
        $this->increment('email_failed_count');
    }

    public function addCost(float $cost): void
    {
        $this->increment('total_cost', $cost);
    }

    public function addEmailCost(float $cost): void
    {
        $this->increment('email_total_cost', $cost);
    }

    // Accessors

    public function getTotalSentAttribute(): int
    {
        return ($this->sent_count ?? 0) + ($this->email_sent_count ?? 0);
    }

    public function getTotalDeliveredAttribute(): int
    {
        return ($this->delivered_count ?? 0) + ($this->email_delivered_count ?? 0);
    }

    public function getTotalFailedAttribute(): int
    {
        return ($this->failed_count ?? 0) + ($this->email_failed_count ?? 0);
    }

    public function getGrandTotalCostAttribute(): float
    {
        return ($this->total_cost ?? 0) + ($this->email_total_cost ?? 0);
    }

    // Owner helper

    public function getOwnerUser(): ?User
    {
        return $this->client?->user;
    }
}
