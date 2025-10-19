<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Menu extends Model
{
    use HasTranslations;

    protected $fillable = [
        'title',
        'slug',
        'url',
        'parent_id',
        'position',
        'target',
        'has_dropdown',
        'is_active',
        'menu_location',
        'icon',
        'meta',
    ];

    public array $translatable = [
        'title',
    ];

    protected $casts = [
        'has_dropdown' => 'boolean',
        'is_active' => 'boolean',
        'meta' => 'array',
        'position' => 'integer',
        'parent_id' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
        'has_dropdown' => false,
        'target' => '_self',
        'menu_location' => 'header',
        'position' => 0,
    ];

    /**
     * Get parent menu
     */
    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    /**
     * Get children menus
     */
    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('position');
    }

    /**
     * Get active children
     */
    public function activeChildren()
    {
        return $this->children()->where('is_active', true);
    }

    /**
     * Scope for active menus
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for header menus
     */
    public function scopeHeader($query)
    {
        return $query->whereIn('menu_location', ['header', 'both']);
    }

    /**
     * Scope for footer menus
     */
    public function scopeFooter($query)
    {
        return $query->whereIn('menu_location', ['footer', 'both']);
    }

    /**
     * Scope for main menus (no parent)
     */
    public function scopeMain($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get formatted URL
     */
    public function getFormattedUrl($locale = 'az')
    {
        if (!$this->url) {
            return null;
        }

        // If URL starts with http or https, return as is (external link)
        if (str_starts_with($this->url, 'http://') || str_starts_with($this->url, 'https://')) {
            return $this->url;
        }

        // For internal links, prepend locale if not default
        if ($locale === 'az') {
            return $this->url;
        }

        return '/' . $locale . $this->url;
    }
}