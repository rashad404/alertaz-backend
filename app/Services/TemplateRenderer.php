<?php

namespace App\Services;

use App\Models\Contact;

class TemplateRenderer
{
    /**
     * Render template with contact attributes
     *
     * @param string $template
     * @param Contact $contact
     * @return string
     */
    public function render(string $template, Contact $contact): string
    {
        $message = $template;
        $attributes = $contact->attributes ?? [];

        // Add phone to available variables
        $attributes['phone'] = $contact->phone;

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
     * Sanitize message for SMS (remove problematic characters)
     *
     * @param string $message
     * @return string
     */
    public function sanitizeForSMS(string $message): string
    {
        // Remove control characters except newlines
        $message = preg_replace('/[\x00-\x09\x0B-\x0C\x0E-\x1F\x7F]/', '', $message);

        // Trim whitespace
        $message = trim($message);

        // Limit length to 500 characters (SMS campaign limit)
        if (mb_strlen($message) > 500) {
            $message = mb_substr($message, 0, 500);
        }

        return $message;
    }

    /**
     * Calculate SMS segments for a message
     *
     * @param string $message
     * @return int
     */
    public function calculateSMSSegments(string $message): int
    {
        $length = mb_strlen($message);

        // Check if message contains unicode characters
        $hasUnicode = mb_strlen($message) !== strlen($message);

        if ($hasUnicode) {
            // Unicode SMS: 70 chars per segment, 67 for concatenated
            if ($length <= 70) {
                return 1;
            }
            return (int) ceil($length / 67);
        } else {
            // GSM 7-bit: 160 chars per segment, 153 for concatenated
            if ($length <= 160) {
                return 1;
            }
            return (int) ceil($length / 153);
        }
    }

    /**
     * Estimate cost for campaign
     *
     * @param string $template
     * @param int $recipientCount
     * @param float $pricePerSMS
     * @return array
     */
    public function estimateCost(string $template, int $recipientCount, float $pricePerSMS = 0.05): array
    {
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
