<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
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
    const STATUS_ACTIVE = 'active';    // For automated campaigns
    const STATUS_PAUSED = 'paused';    // For automated campaigns

    protected $fillable = [
        'client_id',
        'name',
        'sender',
        'message_template',
        'channel',
        'email_subject_template',
        'email_body_template',
        'status',
        'type',
        'check_interval_minutes',
        'cooldown_days',
        'ends_at',
        'run_start_hour',
        'run_end_hour',
        'last_run_at',
        'next_run_at',
        'run_count',
        'segment_filter',
        'scheduled_at',
        'started_at',
        'completed_at',
        'target_count',
        'sent_count',
        'delivered_count',
        'failed_count',
        'email_sent_count',
        'email_delivered_count',
        'email_failed_count',
        'total_cost',
        'email_total_cost',
        'created_by',
        'is_test',
        'balance_warning_20_sent',
        'balance_warning_10_sent',
        'balance_warning_5_sent',
        'pause_reason',
    ];

    protected $casts = [
        'segment_filter' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'ends_at' => 'datetime',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'is_test' => 'boolean',
        'total_cost' => 'decimal:2',
        'email_total_cost' => 'decimal:2',
        'balance_warning_20_sent' => 'boolean',
        'balance_warning_10_sent' => 'boolean',
        'balance_warning_5_sent' => 'boolean',
    ];

    // Relationships
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SmsMessage::class);
    }

    public function contactLogs(): HasMany
    {
        return $this->hasMany(CampaignContactLog::class);
    }

    // Accessors
    protected $appends = ['sent_today_count'];

    public function getSentTodayCountAttribute(): int
    {
        return SmsMessage::where('campaign_id', $this->id)
            ->whereDate('created_at', today())
            ->count();
    }

    // Scopes
    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeAutomated($query)
    {
        return $query->where('type', self::TYPE_AUTOMATED);
    }

    public function scopeOneTime($query)
    {
        return $query->where('type', self::TYPE_ONE_TIME);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePaused($query)
    {
        return $query->where('status', self::STATUS_PAUSED);
    }

    public function scopeDueToRun($query)
    {
        return $query->where('type', self::TYPE_AUTOMATED)
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

    // Helper methods
    public function markAsScheduled(): void
    {
        $this->update(['status' => 'scheduled']);
    }

    public function markAsSending(): void
    {
        $this->update([
            'status' => 'sending',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }

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

    // Automated campaign methods
    public function isAutomated(): bool
    {
        return $this->type === self::TYPE_AUTOMATED;
    }

    public function isOneTime(): bool
    {
        return $this->type === self::TYPE_ONE_TIME;
    }

    public function activate(): void
    {
        $nextRunAt = $this->calculateNextRunTime(now());

        \Log::info('Campaign activate', [
            'campaign_id' => $this->id,
            'run_start_hour' => $this->run_start_hour,
            'run_end_hour' => $this->run_end_hour,
            'current_time' => now()->toDateTimeString(),
            'current_hour' => now()->hour,
            'calculated_next_run_at' => $nextRunAt->toDateTimeString(),
        ]);

        $this->update([
            'status' => self::STATUS_ACTIVE,
            'next_run_at' => $nextRunAt,
            // Reset warning flags on reactivation
            'balance_warning_20_sent' => false,
            'balance_warning_10_sent' => false,
            'balance_warning_5_sent' => false,
            'pause_reason' => null,
        ]);
    }

    public function pause(): void
    {
        $this->update([
            'status' => self::STATUS_PAUSED,
            'next_run_at' => null,
        ]);
    }

    public function pauseWithReason(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_PAUSED,
            'next_run_at' => null,
            'pause_reason' => $reason,
        ]);
    }

    public function resetWarningFlags(): void
    {
        $this->update([
            'balance_warning_20_sent' => false,
            'balance_warning_10_sent' => false,
            'balance_warning_5_sent' => false,
            'pause_reason' => null,
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

    public function hasEnded(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    /**
     * Check if a given hour is within the run window
     *
     * @param int $hour 0-23
     * @return bool
     */
    public function isWithinRunWindow(int $hour): bool
    {
        // If no window set, always within window
        if ($this->run_start_hour === null || $this->run_end_hour === null) {
            return true;
        }

        return $hour >= $this->run_start_hour && $hour < $this->run_end_hour;
    }

    /**
     * Calculate the next run time, ensuring it falls within the run window
     *
     * @param \Carbon\Carbon|null $baseTime Base time to calculate from (defaults to now)
     * @return \Carbon\Carbon
     */
    public function calculateNextRunTime(?\Carbon\Carbon $baseTime = null): \Carbon\Carbon
    {
        $nextRun = $baseTime ?? now();

        // If no window set, return as-is
        if ($this->run_start_hour === null || $this->run_end_hour === null) {
            return $nextRun;
        }

        // If current time is within window, return as-is
        if ($this->isWithinRunWindow($nextRun->hour)) {
            return $nextRun;
        }

        // If before start hour, schedule for today's start hour
        if ($nextRun->hour < $this->run_start_hour) {
            return $nextRun->copy()->setTime($this->run_start_hour, 0, 0);
        }

        // If after end hour (or equal), schedule for tomorrow's start hour
        return $nextRun->copy()->addDay()->setTime($this->run_start_hour, 0, 0);
    }

    /**
     * Get the user who owns this campaign (via client)
     *
     * @return \App\Models\User|null
     */
    public function getOwnerUser(): ?\App\Models\User
    {
        return $this->client?->user;
    }

    /**
     * Check if campaign requires phone numbers (SMS channel)
     */
    public function requiresPhone(): bool
    {
        $channel = $this->channel ?? self::CHANNEL_SMS;
        return $channel === self::CHANNEL_SMS || $channel === self::CHANNEL_BOTH;
    }

    /**
     * Check if campaign requires email addresses
     */
    public function requiresEmail(): bool
    {
        $channel = $this->channel ?? self::CHANNEL_SMS;
        return $channel === self::CHANNEL_EMAIL || $channel === self::CHANNEL_BOTH;
    }

    /**
     * Check if campaign is SMS-only
     */
    public function isSmsOnly(): bool
    {
        $channel = $this->channel ?? self::CHANNEL_SMS;
        return $channel === self::CHANNEL_SMS;
    }

    /**
     * Check if campaign is Email-only
     */
    public function isEmailOnly(): bool
    {
        return $this->channel === self::CHANNEL_EMAIL;
    }

    /**
     * Check if campaign uses both channels
     */
    public function usesBothChannels(): bool
    {
        return $this->channel === self::CHANNEL_BOTH;
    }

    /**
     * Get email messages relationship
     */
    public function emailMessages(): HasMany
    {
        return $this->hasMany(\App\Models\EmailMessage::class);
    }

    /**
     * Increment email sent count
     */
    public function incrementEmailSentCount(): void
    {
        $this->increment('email_sent_count');
    }

    /**
     * Increment email delivered count
     */
    public function incrementEmailDeliveredCount(): void
    {
        $this->increment('email_delivered_count');
    }

    /**
     * Increment email failed count
     */
    public function incrementEmailFailedCount(): void
    {
        $this->increment('email_failed_count');
    }

    /**
     * Estimate total cost for the campaign (including both channels)
     *
     * @return array
     */
    public function estimateTotalCost(): array
    {
        $templateRenderer = app(\App\Services\TemplateRenderer::class);
        $costPerSms = config('app.sms_cost_per_message', 0.04);
        $costPerEmail = config('app.email_cost_per_message', 0.01);

        $result = [
            'target_count' => $this->target_count,
            'cost_per_sms' => $costPerSms,
            'cost_per_email' => $costPerEmail,
            'estimated_sms_cost' => 0,
            'estimated_email_cost' => 0,
            'estimated_total_cost' => 0,
        ];

        // Calculate SMS cost
        if ($this->requiresPhone()) {
            $segments = $templateRenderer->calculateSMSSegments($this->message_template ?? '');
            $result['segments_per_message'] = $segments;
            $result['estimated_sms_cost'] = round($this->target_count * $segments * $costPerSms, 2);
        }

        // Calculate Email cost
        if ($this->requiresEmail()) {
            $result['estimated_email_cost'] = round($this->target_count * $costPerEmail, 2);
        }

        $result['estimated_total_cost'] = $result['estimated_sms_cost'] + $result['estimated_email_cost'];

        return $result;
    }
}
