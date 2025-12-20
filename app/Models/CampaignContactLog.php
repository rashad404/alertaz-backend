<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignContactLog extends Model
{
    public $timestamps = false;

    protected $table = 'campaign_contact_log';

    protected $fillable = [
        'campaign_id',
        'contact_id',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
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

    // Check if contact is in cooldown period
    public static function isInCooldown(int $campaignId, int $contactId, int $cooldownDays): bool
    {
        return static::where('campaign_id', $campaignId)
            ->where('contact_id', $contactId)
            ->where('sent_at', '>', now()->subDays($cooldownDays))
            ->exists();
    }

    // Record that a message was sent to a contact
    public static function recordSend(int $campaignId, int $contactId): self
    {
        return static::updateOrCreate(
            [
                'campaign_id' => $campaignId,
                'contact_id' => $contactId,
            ],
            [
                'sent_at' => now(),
            ]
        );
    }
}
