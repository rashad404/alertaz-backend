<?php

namespace App\Services;

use App\Exceptions\TemplateRenderException;
use App\Models\Contact;

class TemplateRenderer
{
    /**
     * Render template with contact attributes
     *
     * @param string $template
     * @param Contact $contact
     * @param array|null $segmentFilter Optional segment filter to determine which array item matched
     * @return string
     */
    public function render(string $template, Contact $contact, ?array $segmentFilter = null): string
    {
        $message = $template;
        $attributes = $contact->attributes ?? [];

        // Add phone to available variables
        $attributes['phone'] = $contact->phone;

        // Extract array item fields (hosting_name, hosting_expiry, domain_name, domain_expiry)
        $attributes = $this->extractArrayItemFields($attributes, $template, $segmentFilter);

        // Replace all {{variable}} placeholders
        foreach ($attributes as $key => $value) {
            // Convert arrays/objects to JSON string
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            // Convert to string
            $value = (string) $value;

            // Replace placeholder
            $message = str_replace("{{" . $key . "}}", $value, $message);
        }

        return $message;
    }

    /**
     * Extract fields from array items dynamically based on template variables
     * Auto-detects patterns like {{xxx_name}}, {{xxx_expiry}} and finds matching arrays
     *
     * Example: {{hosting_name}} will look for arrays: hostings, hosting_list, hosting
     * and extract the 'name' field from the matched item
     *
     * @param array $attributes
     * @param string $template
     * @param array|null $segmentFilter
     * @return array
     */
    protected function extractArrayItemFields(array $attributes, string $template, ?array $segmentFilter = null): array
    {
        // Find all template variables
        preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $template, $matches);
        $templateVars = array_unique($matches[1] ?? []);

        // Group variables by potential array prefix (e.g., hosting_name, hosting_expiry -> hosting)
        $prefixGroups = [];
        foreach ($templateVars as $var) {
            // Skip if variable already exists in attributes
            if (isset($attributes[$var])) {
                continue;
            }

            // Check if variable follows pattern: prefix_field (e.g., hosting_name, domain_expiry)
            if (preg_match('/^([a-zA-Z0-9]+)_([a-zA-Z0-9_]+)$/', $var, $varMatch)) {
                $prefix = $varMatch[1];
                $field = $varMatch[2];

                if (!isset($prefixGroups[$prefix])) {
                    $prefixGroups[$prefix] = [];
                }
                $prefixGroups[$prefix][$field] = $var; // field => template_var
            }
        }

        // For each prefix group, try to find a matching array in attributes
        foreach ($prefixGroups as $prefix => $fieldMappings) {
            // Try common array naming patterns
            $possibleArrayKeys = [
                $prefix . 's',      // hosting -> hostings
                $prefix . '_list',  // vps -> vps_list
                $prefix . 'es',     // box -> boxes
                $prefix,            // if already plural or exact match
            ];

            $arrayKey = null;
            $items = null;

            foreach ($possibleArrayKeys as $key) {
                if (isset($attributes[$key]) && is_array($attributes[$key]) && !empty($attributes[$key])) {
                    // Check if it's an array of objects (not a simple array)
                    $firstItem = reset($attributes[$key]);
                    if (is_array($firstItem)) {
                        $arrayKey = $key;
                        $items = $attributes[$key];
                        break;
                    }
                }
            }

            if (!$items) {
                continue;
            }

            // Find the best matching item from the array
            $matchedItem = $this->findMatchingArrayItem($items, $arrayKey, $segmentFilter);

            if ($matchedItem) {
                // Extract all requested fields from the matched item
                foreach ($fieldMappings as $field => $templateVar) {
                    if (isset($matchedItem[$field])) {
                        $attributes[$templateVar] = $matchedItem[$field];
                    }
                }
            }
        }

        return $attributes;
    }

    /**
     * Find the best matching item from an array based on segment filter or default logic
     * Works with any array structure - automatically detects expiry/active fields
     *
     * @param array $items
     * @param string $arrayKey
     * @param array|null $segmentFilter
     * @return array|null
     */
    protected function findMatchingArrayItem(array $items, string $arrayKey, ?array $segmentFilter = null): ?array
    {
        // If segment filter specifies a condition on this array, find the matching item
        if ($segmentFilter && isset($segmentFilter['conditions'])) {
            foreach ($segmentFilter['conditions'] as $condition) {
                if (isset($condition['key']) && $condition['key'] === $arrayKey) {
                    $operator = $condition['operator'] ?? '';
                    $value = $condition['value'] ?? null;

                    // Handle expiry-based operators (works with any 'expiry' field)
                    $expiryOperators = [
                        'any_expiry_in_days', 'any_expiry_within', 'any_expiry_today',
                        'any_expiry_after', 'any_expiry_expired_since'
                    ];

                    if (in_array($operator, $expiryOperators)) {
                        $matchedItem = $this->findItemByExpiryCondition($items, $operator, $value);
                        if ($matchedItem) {
                            return $matchedItem;
                        }
                    }
                }
            }
        }

        // Default fallback: find best item based on common patterns
        return $this->findBestDefaultItem($items);
    }

    /**
     * Find an item that matches an expiry-based condition
     *
     * @param array $items
     * @param string $operator
     * @param mixed $value
     * @return array|null
     */
    protected function findItemByExpiryCondition(array $items, string $operator, $value): ?array
    {
        $today = now()->startOfDay();

        foreach ($items as $item) {
            // Look for any date field (expiry, expiry_date, expires_at, etc.)
            $expiryValue = $item['expiry'] ?? $item['expiry_date'] ?? $item['expires_at'] ?? null;
            if (!$expiryValue) continue;

            try {
                $expiryDate = \Carbon\Carbon::parse($expiryValue)->startOfDay();
                $daysUntilExpiry = $today->diffInDays($expiryDate, false);

                switch ($operator) {
                    case 'any_expiry_today':
                        if ($daysUntilExpiry === 0) return $item;
                        break;
                    case 'any_expiry_in_days':
                        if ($daysUntilExpiry == $value) return $item;
                        break;
                    case 'any_expiry_within':
                        if ($daysUntilExpiry >= 0 && $daysUntilExpiry <= $value) return $item;
                        break;
                    case 'any_expiry_after':
                        if ($daysUntilExpiry > $value) return $item;
                        break;
                    case 'any_expiry_expired_since':
                        if ($daysUntilExpiry < 0 && abs($daysUntilExpiry) <= $value) return $item;
                        break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Find the best default item from an array (first active, or soonest expiring)
     *
     * @param array $items
     * @return array|null
     */
    protected function findBestDefaultItem(array $items): ?array
    {
        if (empty($items)) {
            return null;
        }

        // Try to find active items (check common active/status field patterns)
        $activeItems = array_filter($items, function($item) {
            // Check various "active" field patterns
            if (isset($item['active']) && $item['active']) return true;
            if (isset($item['is_active']) && $item['is_active']) return true;
            if (isset($item['status']) && in_array($item['status'], ['active', 'enabled', 'valid'])) return true;
            return false;
        });

        $itemsToSort = !empty($activeItems) ? $activeItems : $items;

        // Sort by expiry date (soonest first) - check common expiry field patterns
        usort($itemsToSort, function($a, $b) {
            $expiryA = $a['expiry'] ?? $a['expiry_date'] ?? $a['expires_at'] ?? '9999-12-31';
            $expiryB = $b['expiry'] ?? $b['expiry_date'] ?? $b['expires_at'] ?? '9999-12-31';
            return strcmp($expiryA, $expiryB);
        });

        return reset($itemsToSort) ?: null;
    }

    /**
     * Render template with strict mode - throws exception on unresolved variables
     *
     * @param string $template
     * @param Contact $contact
     * @return string
     * @throws TemplateRenderException
     */
    public function renderStrict(string $template, Contact $contact): string
    {
        $message = $this->render($template, $contact);

        // Check for remaining placeholders
        if (preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $message, $matches)) {
            $unresolvedVariables = array_unique($matches[1]);
            throw new TemplateRenderException(
                'Unresolved template variables: ' . implode(', ', $unresolvedVariables),
                $unresolvedVariables
            );
        }

        return $message;
    }

    /**
     * Render template with fallback - unresolved variables become their names
     * Use this for test sends where the sample contact may not have all attributes
     *
     * @param string $template
     * @param Contact $contact
     * @return string
     */
    public function renderWithFallback(string $template, Contact $contact): string
    {
        $message = $this->render($template, $contact);

        // Replace remaining {{variable}} with just the variable name
        $message = preg_replace_callback('/\{\{([a-zA-Z0-9_]+)\}\}/', function ($matches) {
            return $matches[1]; // Return just the variable name without braces
        }, $message);

        return $message;
    }

    /**
     * Validate template (check for undefined variables)
     *
     * @param string $template
     * @param array $availableKeys
     * @return array Returns array of undefined variables
     */
    public function validateTemplate(string $template, array $availableKeys): array
    {
        // Add phone as always available
        $availableKeys[] = 'phone';

        // Find all {{variable}} patterns
        preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $template, $matches);
        $usedVariables = $matches[1] ?? [];

        // Find variables that are used but not available
        $undefinedVariables = array_diff($usedVariables, $availableKeys);

        return array_values($undefinedVariables);
    }

    /**
     * Get all variables used in template
     *
     * @param string $template
     * @return array
     */
    public function extractVariables(string $template): array
    {
        preg_match_all('/\{\{([a-zA-Z0-9_]+)\}\}/', $template, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Preview template with sample data
     *
     * @param string $template
     * @param array $sampleData
     * @return string
     */
    public function preview(string $template, array $sampleData): string
    {
        $message = $template;

        foreach ($sampleData as $key => $value) {
            // Convert arrays/objects to JSON string
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            // Convert to string
            $value = (string) $value;

            // Replace placeholder
            $message = str_replace("{{" . $key . "}}", $value, $message);
        }

        return $message;
    }

    /**
     * Sanitize message for SMS (GSM-7 compatible)
     *
     * @param string $message
     * @return string
     */
    public function sanitizeForSMS(string $message): string
    {
        // Replace Azerbaijani characters with Latin equivalents
        $message = $this->replaceAzerbaijaniChars($message);

        // Remove any remaining non-GSM7 characters (Unicode)
        $message = $this->removeNonGSM7Chars($message);

        // Remove control characters except newlines
        $message = preg_replace('/[\x00-\x09\x0B-\x0C\x0E-\x1F\x7F]/', '', $message);

        // Trim whitespace
        $message = trim($message);

        // Limit length to max message length from config
        $maxLength = config('app.sms_max_message_length', 500);
        if (mb_strlen($message) > $maxLength) {
            $message = mb_substr($message, 0, $maxLength);
        }

        return $message;
    }

    /**
     * Replace Azerbaijani special characters with Latin equivalents
     *
     * @param string $message
     * @return string
     */
    protected function replaceAzerbaijaniChars(string $message): string
    {
        $replacements = [
            'ə' => 'e', 'Ə' => 'E',
            'ç' => 'c', 'Ç' => 'C',
            'ş' => 's', 'Ş' => 'S',
            'ğ' => 'g', 'Ğ' => 'G',
            'ü' => 'u', 'Ü' => 'U',
            'ö' => 'o', 'Ö' => 'O',
            'ı' => 'i', 'İ' => 'I',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * Remove non-GSM7 characters (keep only ASCII printable + basic punctuation)
     *
     * @param string $message
     * @return string
     */
    protected function removeNonGSM7Chars(string $message): string
    {
        // GSM-7 basic character set (printable ASCII + common symbols)
        // Keep: a-z, A-Z, 0-9, space, newline, and common punctuation
        return preg_replace('/[^\x20-\x7E\n]/', '', $message);
    }

    /**
     * Calculate SMS segments for a message (GSM-7 only, Unicode blocked)
     *
     * @param string $message
     * @return int
     */
    public function calculateSMSSegments(string $message): int
    {
        $length = mb_strlen($message);
        $charsPerSegment = config('app.sms_chars_per_segment', 153);

        // GSM 7-bit: 160 chars for single, configured value for concatenated
        if ($length <= 160) {
            return 1;
        }

        return (int) ceil($length / $charsPerSegment);
    }

    /**
     * Estimate cost for campaign
     *
     * @param string $template
     * @param int $recipientCount
     * @param float $pricePerSMS
     * @return array
     */
    public function estimateCost(string $template, int $recipientCount, ?float $pricePerSMS = null): array
    {
        $pricePerSMS = $pricePerSMS ?? config('app.sms_cost_per_message', 0.04);
        $segments = $this->calculateSMSSegments($template);
        $totalMessages = $recipientCount * $segments;
        $estimatedCost = $totalMessages * $pricePerSMS;

        return [
            'segments_per_message' => $segments,
            'total_sms_count' => $totalMessages,
            'estimated_cost' => round($estimatedCost, 2),
            'price_per_sms' => $pricePerSMS,
        ];
    }
}
