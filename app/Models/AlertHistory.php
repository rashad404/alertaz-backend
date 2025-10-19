<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlertHistory extends Model
{
    use HasFactory;

    protected $table = 'alert_history';

    protected $fillable = [
        'personal_alert_id',
        'user_id',
        'triggered_conditions',
        'current_values',
        'notification_channels',
        'delivery_status',
        'message',
        'triggered_at',
    ];

    protected $casts = [
        'triggered_conditions' => 'array',
        'current_values' => 'array',
        'notification_channels' => 'array',
        'delivery_status' => 'array',
        'triggered_at' => 'datetime',
    ];

    /**
     * Get the personal alert.
     */
    public function personalAlert()
    {
        return $this->belongsTo(PersonalAlert::class);
    }

    /**
     * Get the user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if notification was successfully delivered to all channels.
     */
    public function isFullyDelivered()
    {
        if (empty($this->delivery_status)) {
            return false;
        }

        foreach ($this->delivery_status as $channel => $status) {
            if ($status !== 'delivered' && $status !== 'success') {
                return false;
            }
        }

        return true;
    }

    /**
     * Get failed delivery channels.
     */
    public function getFailedChannels()
    {
        $failed = [];

        if (!empty($this->delivery_status)) {
            foreach ($this->delivery_status as $channel => $status) {
                if ($status === 'failed' || $status === 'error') {
                    $failed[] = $channel;
                }
            }
        }

        return $failed;
    }
}