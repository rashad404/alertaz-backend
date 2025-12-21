<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    use HasFactory;

    protected $table = 'otp_verifications';

    protected $fillable = [
        'type',
        'phone',
        'email',
        'code',
        'purpose',
        'attempts',
        'expires_at',
        'verified_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Scope for active OTPs.
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now())
            ->whereNull('verified_at');
    }

    /**
     * Check if OTP is expired.
     */
    public function isExpired()
    {
        return $this->expires_at < now();
    }

    /**
     * Check if OTP is verified.
     */
    public function isVerified()
    {
        return $this->verified_at !== null;
    }
}