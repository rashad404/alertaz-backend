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
        'status',
        'type',
        'check_interval_minutes',
        'cooldown_days',
        'ends_at',
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
        'total_cost',
        'created_by',
        'is_test',
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
        return $this->hasMany(CampaignMessage::class);
    }

    public function contactLogs(): HasMany
    {
        return $this->hasMany(CampaignContactLog::class);
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
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'next_run_at' => now(),
        ]);
    }

    public function pause(): void
    {
        $this->update([
            'status' => self::STATUS_PAUSED,
            'next_run_at' => null,
        ]);
    }

    public function scheduleNextRun(): void
    {
        if ($this->check_interval_minutes) {
            $this->update([
                'last_run_at' => now(),
                'next_run_at' => now()->addMinutes($this->check_interval_minutes),
                'run_count' => $this->run_count + 1,
            ]);
        }
    }

    public function hasEnded(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }
}
