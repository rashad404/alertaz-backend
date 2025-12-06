<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBalance extends Model
{
    protected $fillable = [
        'user_id',
        'sms_credits',
        'total_spent',
        'last_recharged_at',
    ];

    protected $casts = [
        'sms_credits' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'last_recharged_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addCredits(float $amount): void
    {
        $this->increment('sms_credits', $amount);
        $this->last_recharged_at = now();
        $this->save();
    }

    public function deductCredits(float $amount): bool
    {
        if ($this->sms_credits < $amount) {
            return false;
        }

        $this->decrement('sms_credits', $amount);
        $this->increment('total_spent', $amount);
        $this->save();

        return true;
    }

    public function hasEnoughCredits(float $amount): bool
    {
        return $this->sms_credits >= $amount;
    }
}
