<?php

namespace App\Services;

use App\Models\AlertParseCache;
use Illuminate\Support\Str;

class AlertCacheService
{
    /**
     * Normalize user input into a pattern with placeholders
     * Example: "Bitcoin $100k" -> "crypto_price_above_{value}"
     *
     * @param string $input
     * @return array ['pattern' => string, 'variables' => array]
     */
    public function normalizeInput(string $input): array
    {
        $input = strtolower(trim($input));

        // Extract crypto mentions (bitcoin, btc, ethereum, eth, etc.)
        $cryptos = ['bitcoin', 'btc', 'ethereum', 'eth', 'solana', 'sol', 'cardano', 'ada'];
        $detectedCrypto = null;

        foreach ($cryptos as $crypto) {
            if (str_contains($input, $crypto)) {
                $detectedCrypto = $crypto;
                $input = str_replace($crypto, '{crypto}', $input);
                break;
            }
        }

        // Extract numbers (prices, values)
        $numbers = [];
        preg_match_all('/\$?(\d+(?:,\d{3})*(?:\.\d+)?)[k]?/', $input, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $match) {
                $numbers[] = str_replace(',', '', $match);
            }

            // Replace numbers with {value} placeholder
            $input = preg_replace('/\$?(\d+(?:,\d{3})*(?:\.\d+)?)[k]?/', '{value}', $input);
        }

        // Extract operators
        $operators = [
            'above' => ['above', 'over', 'more than', 'greater than', '>', 'goes over'],
            'below' => ['below', 'under', 'less than', '<', 'drops below', 'falls below'],
            'equals' => ['equals', '=', 'is', 'reaches'],
        ];

        $detectedOperator = null;
        foreach ($operators as $operator => $variants) {
            foreach ($variants as $variant) {
                if (str_contains($input, $variant)) {
                    $detectedOperator = $operator;
                    $input = str_replace($variant, '{operator}', $input);
                    break 2;
                }
            }
        }

        // Clean up the pattern (remove extra spaces, special chars)
        $pattern = preg_replace('/\s+/', '_', $input);
        $pattern = preg_replace('/[^a-z0-9_{}-]/', '', $pattern);
        $pattern = trim($pattern, '_');

        // Build variables array
        $variables = [];
        if ($detectedCrypto) {
            $variables['crypto'] = $detectedCrypto;
        }
        if ($detectedOperator) {
            $variables['operator'] = $detectedOperator;
        }
        if (!empty($numbers)) {
            $variables['value'] = $numbers[0];  // Use first number
        }

        return [
            'pattern' => $pattern,
            'variables' => $variables,
        ];
    }

    /**
     * Find cached parse result by pattern
     *
     * @param string $pattern
     * @return AlertParseCache|null
     */
    public function findCached(string $pattern): ?AlertParseCache
    {
        return AlertParseCache::findByPattern($pattern);
    }

    /**
     * Cache a new parse result
     *
     * @param string $inputText
     * @param string $pattern
     * @param array $variables
     * @param array $parseResult
     * @param float $confidence
     * @param string $aiProvider
     * @return AlertParseCache
     */
    public function cache(
        string $inputText,
        string $pattern,
        array $variables,
        array $parseResult,
        float $confidence,
        string $aiProvider
    ): AlertParseCache {
        return AlertParseCache::create([
            'input_text' => $inputText,
            'normalized_pattern' => $pattern,
            'extracted_variables' => $variables,
            'parsed_result' => $parseResult,
            'confidence' => $confidence,
            'usage_count' => 1,
            'ai_provider' => $aiProvider,
        ]);
    }

    /**
     * Apply new variables to cached result
     *
     * @param AlertParseCache $cached
     * @param array $newVariables
     * @return array
     */
    public function applyVariables(AlertParseCache $cached, array $newVariables): array
    {
        $result = $cached->parsed_result;

        // Update the result with new variables
        foreach ($newVariables as $key => $value) {
            if ($key === 'crypto' && isset($result['crypto_symbol'])) {
                $result['crypto_symbol'] = strtoupper($value === 'bitcoin' ? 'BTC' : $value);
                $result['crypto_id'] = $value;
            } elseif ($key === 'value' && isset($result['value'])) {
                // Handle 'k' suffix (e.g., "100k" = 100000)
                if (str_ends_with($value, 'k')) {
                    $value = ((float) rtrim($value, 'k')) * 1000;
                }
                $result['value'] = (float) $value;
            } elseif ($key === 'operator' && isset($result['operator'])) {
                $result['operator'] = $value;
            }
        }

        return $result;
    }

    /**
     * Cleanup old cache entries (run periodically)
     */
    public function cleanup(): int
    {
        $ttlDays = config('ai.parsing.cache_ttl_days', 90);

        return AlertParseCache::where('created_at', '<', now()->subDays($ttlDays))
            ->delete();
    }
}
