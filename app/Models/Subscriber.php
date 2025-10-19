<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Subscriber extends Model
{
    protected $fillable = [
        'email',
        'language',
        'status',
        'token',
        'subscribed_at',
        'ip_address'
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($subscriber) {
            // Generate unique token for unsubscribe
            if (empty($subscriber->token)) {
                $subscriber->token = Str::random(64);
            }
            
            // Set subscribed_at if not provided
            if (empty($subscriber->subscribed_at)) {
                $subscriber->subscribed_at = now();
            }
        });
    }

    /**
     * Scope to get only active subscribers
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get subscribers by language
     */
    public function scopeByLanguage($query, $language)
    {
        return $query->where('language', $language);
    }

    /**
     * Unsubscribe the user
     */
    public function unsubscribe()
    {
        $this->update(['status' => 'unsubscribed']);
    }

    /**
     * Resubscribe the user
     */
    public function resubscribe()
    {
        $this->update([
            'status' => 'active',
            'subscribed_at' => now()
        ]);
    }
}