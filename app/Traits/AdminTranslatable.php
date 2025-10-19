<?php

namespace App\Traits;

trait AdminTranslatable
{
    /**
     * Get the admin default language
     */
    protected function getAdminLanguage(): string
    {
        return config('admin.default_language', 'en');
    }
    
    /**
     * Get translated value from JSON or array field
     */
    protected function getTranslatedValue($value, ?string $language = null): ?string
    {
        $language = $language ?? $this->getAdminLanguage();
        
        // If already a string, return as is
        if (is_string($value)) {
            // Try to decode JSON
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->extractTranslation($decoded, $language);
            }
            return $value;
        }
        
        // If array, extract translation
        if (is_array($value)) {
            return $this->extractTranslation($value, $language);
        }
        
        // If object with getTranslation method
        if (is_object($value) && method_exists($value, 'getTranslation')) {
            return $value->getTranslation($language);
        }
        
        return null;
    }
    
    /**
     * Extract translation from array with fallbacks
     */
    private function extractTranslation(array $translations, string $language): ?string
    {
        // Try requested language
        if (isset($translations[$language])) {
            return $translations[$language];
        }
        
        // Fallback order: en -> az -> ru -> first available
        $fallbackOrder = ['en', 'az', 'ru'];
        foreach ($fallbackOrder as $fallback) {
            if (isset($translations[$fallback])) {
                return $translations[$fallback];
            }
        }
        
        // Return first available translation
        return !empty($translations) ? reset($translations) : null;
    }
}