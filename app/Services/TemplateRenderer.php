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
