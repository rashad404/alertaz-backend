<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class ContactInfo extends Model
{
    use HasTranslations;

    protected $table = 'contact_info';

    protected $fillable = [
        'company_name',
        'legal_name',
        'voen',
        'chief_editor',
        'domain_owner',
        'address',
        'phone',
        'phone_2',
        'email',
        'email_2',
        'working_hours',
        'facebook_url',
        'instagram_url',
        'linkedin_url',
        'twitter_url',
        'youtube_url',
        'latitude',
        'longitude',
        'map_embed_url'
    ];

    public array $translatable = [
        'address',
        'working_hours',
        'chief_editor'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * Get social media links as array
     */
    public function getSocialLinksAttribute()
    {
        $links = [];
        
        if ($this->facebook_url) $links['facebook'] = $this->facebook_url;
        if ($this->instagram_url) $links['instagram'] = $this->instagram_url;
        if ($this->linkedin_url) $links['linkedin'] = $this->linkedin_url;
        if ($this->twitter_url) $links['twitter'] = $this->twitter_url;
        if ($this->youtube_url) $links['youtube'] = $this->youtube_url;
        
        return $links;
    }
}