<?php

namespace App\Services;

class TemplateRenderer
{
    /**
     * Render template with variables
     */
    public function render(string $template, array $variables): string
    {
        $message = $template;

        foreach ($variables as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            $message = str_replace("{{" . $key . "}}", (string) $value, $message);
        }

        return $message;
    }

    /**
     * Extract variables from template
     */
    public function extractVariables(string $template): array
    {
        preg_match_all('/\{\{(\w+)\}\}/', $template, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Calculate SMS segments
     */
    public function calculateSMSSegments(string $message): int
    {
        $length = mb_strlen($message);
        if ($length <= 160) return 1;
        return (int) ceil($length / 153);
    }

    /**
     * Sanitize for SMS (GSM-7)
     */
    public function sanitizeForSMS(string $message): string
    {
        // Replace Azerbaijani chars
        $replacements = [
            'ə' => 'e', 'Ə' => 'E', 'ç' => 'c', 'Ç' => 'C',
            'ş' => 's', 'Ş' => 'S', 'ğ' => 'g', 'Ğ' => 'G',
            'ü' => 'u', 'Ü' => 'U', 'ö' => 'o', 'Ö' => 'O',
            'ı' => 'i', 'İ' => 'I',
        ];
        $message = str_replace(array_keys($replacements), array_values($replacements), $message);

        // Keep only GSM-7 chars
        return preg_replace('/[^\x20-\x7E\n]/', '', $message);
    }

    /**
     * Estimate cost
     */
    public function estimateCost(string $template, int $count, float $pricePerSms = 0.04): array
    {
        $segments = $this->calculateSMSSegments($template);
        return [
            'segments' => $segments,
            'total_sms' => $count * $segments,
            'cost' => round($count * $segments * $pricePerSms, 2),
        ];
    }
}
