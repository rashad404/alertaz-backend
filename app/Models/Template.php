<?php

namespace App\Models;

use App\Traits\BelongsToClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Template extends Model
{
    use BelongsToClient;

    const CHANNEL_SMS = 'sms';
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_BOTH = 'both';

    protected $fillable = [
        'client_id',
        'name',
        'channel',
        'message_template',
        'email_subject',
        'email_body',
        'variables',
        'is_default',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_default' => 'boolean',
    ];

    /**
     * Scope for SMS templates
     */
    public function scopeSms(Builder $query): Builder
    {
        return $query->whereIn('channel', [self::CHANNEL_SMS, self::CHANNEL_BOTH]);
    }

    /**
     * Scope for email templates
     */
    public function scopeEmail(Builder $query): Builder
    {
        return $query->whereIn('channel', [self::CHANNEL_EMAIL, self::CHANNEL_BOTH]);
    }

    /**
     * Scope for default templates
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Check if template supports SMS
     */
    public function supportsSms(): bool
    {
        return in_array($this->channel, [self::CHANNEL_SMS, self::CHANNEL_BOTH]);
    }

    /**
     * Check if template supports email
     */
    public function supportsEmail(): bool
    {
        return in_array($this->channel, [self::CHANNEL_EMAIL, self::CHANNEL_BOTH]);
    }

    /**
     * Extract variables from templates
     */
    public function extractVariables(): array
    {
        $variables = [];

        // Extract from SMS template
        if ($this->message_template) {
            preg_match_all('/\{\{(\w+)\}\}/', $this->message_template, $matches);
            $variables = array_merge($variables, $matches[1]);
        }

        // Extract from email subject
        if ($this->email_subject) {
            preg_match_all('/\{\{(\w+)\}\}/', $this->email_subject, $matches);
            $variables = array_merge($variables, $matches[1]);
        }

        // Extract from email body
        if ($this->email_body) {
            preg_match_all('/\{\{(\w+)\}\}/', $this->email_body, $matches);
            $variables = array_merge($variables, $matches[1]);
        }

        return array_unique($variables);
    }

    /**
     * Update variables list from template content
     */
    public function updateVariables(): void
    {
        $this->variables = $this->extractVariables();
        $this->save();
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($template) {
            // Auto-extract variables on save
            $template->variables = $template->extractVariables();
        });
    }
}
