<?php

namespace App\Models;

use App\Helpers\EmailValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = [
        'client_id',
        'phone',
        'attributes',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    // Relationships
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function campaignMessages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class);
    }

    // Scopes
    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    // Helper methods
    public function getContactAttribute(string $key)
    {
        $attrs = $this->attributes;
        return is_array($attrs) ? ($attrs[$key] ?? null) : null;
    }

    /**
     * Check if contact has a phone number
     */
    public function hasPhone(): bool
    {
        return !empty($this->phone);
    }

    /**
     * Check if contact has an email address
     */
    public function hasEmail(): bool
    {
        return !empty($this->getEmailForValidation());
    }

    /**
     * Check if contact can receive SMS
     */
    public function canReceiveSms(): bool
    {
        return $this->hasPhone();
    }

    /**
     * Check if contact can receive email
     */
    public function canReceiveEmail(): bool
    {
        $email = $this->getEmailForValidation();
        return EmailValidator::isValid($email);
    }

    /**
     * Get email from attributes JSON
     */
    public function getEmailForValidation(): ?string
    {
        $attrs = $this->getAttributeValue('attributes');
        if (is_array($attrs) && !empty($attrs['email'])) {
            return $attrs['email'];
        }

        return null;
    }
}
