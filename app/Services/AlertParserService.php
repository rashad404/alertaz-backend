<?php

namespace App\Services;

use App\Services\AI\AIProviderFactory;
use Illuminate\Support\Facades\Log;

class AlertParserService
{
    protected $cacheService;

    public function __construct(AlertCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Parse natural language alert input
     *
     * @param string $input User input like "Bitcoin $100k"
     * @return array Parsed alert configuration
     */
    public function parse(string $input): array
    {
        // Validate input length
        $maxLength = config('ai.parsing.max_input_length', 200);
        if (strlen($input) > $maxLength) {
            throw new \Exception("Input too long. Maximum {$maxLength} characters.");
        }

        // Normalize input to extract pattern
        $normalized = $this->cacheService->normalizeInput($input);
        $pattern = $normalized['pattern'];
        $variables = $normalized['variables'];

        Log::info('[AlertParser] Normalized input', [
            'input' => $input,
            'pattern' => $pattern,
            'variables' => $variables,
        ]);

        // Check cache first
        $cached = $this->cacheService->findCached($pattern);

        if ($cached) {
            Log::info('[AlertParser] Cache HIT for pattern: ' . $pattern);

            // Increment usage count
            $cached->incrementUsage();

            // Apply new variables to cached result
            $result = $this->cacheService->applyVariables($cached, $variables);

            return [
                'cached' => true,
                'pattern' => $pattern,
                ...$result,
            ];
        }

        Log::info('[AlertParser] Cache MISS - calling LLM');

        // Cache miss - call LLM
        $parseResult = $this->parseWithLLM($input);

        // Cache the result
        $this->cacheService->cache(
            $input,
            $pattern,
            $variables,
            $parseResult,
            $parseResult['confidence'] ?? 1.0,
            config('ai.default_provider')
        );

        return [
            'cached' => false,
            'pattern' => $pattern,
            ...$parseResult,
        ];
    }

    /**
     * Parse input using LLM
     *
     * @param string $input
     * @return array
     */
    protected function parseWithLLM(string $input): array
    {
        $provider = AIProviderFactory::make();

        $prompt = $this->buildPrompt($input);

        try {
            // Use parsing-specific max tokens
            $maxTokens = config('ai.providers.' . config('ai.default_provider') . '.max_tokens_parsing', 500);

            $response = $provider->generateText($prompt, $maxTokens);

            Log::info('[AlertParser] LLM Response', ['response' => $response]);

            // Parse JSON response
            $result = $this->parseResponse($response);

            // Validate confidence
            $minConfidence = config('ai.parsing.min_confidence', 0.7);
            if (($result['confidence'] ?? 0) < $minConfidence) {
                throw new \Exception("Low confidence parse result: " . ($result['confidence'] ?? 0));
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('[AlertParser] LLM parsing failed', [
                'error' => $e->getMessage(),
                'input' => $input,
            ]);

            throw new \Exception('Failed to parse alert: ' . $e->getMessage());
        }
    }

    /**
     * Build prompt for LLM
     *
     * @param string $input
     * @return string
     */
    protected function buildPrompt(string $input): string
    {
        return <<<PROMPT
You are an expert at parsing natural language into structured alert configurations.

User input: "{$input}"

Available services:
- crypto (cryptocurrencies like Bitcoin, Ethereum, etc.)
- website (monitor website uptime like google.com, mysite.az)
- stocks (NOT YET AVAILABLE)
- currency (NOT YET AVAILABLE)
- weather (NOT YET AVAILABLE)

For CRYPTO alerts, available operators:
- above: price goes above threshold
- below: price goes below threshold
- equals: price equals threshold

For WEBSITE alerts:
- condition: "down" (alert when website is down) or "up" (alert when website is up)
- url: the website URL to monitor (can be with or without http/https)

Parse the input and return ONLY a JSON object with this structure:

For CRYPTO:
{
  "service": "crypto",
  "crypto_id": "bitcoin",
  "crypto_symbol": "BTC",
  "operator": "above",
  "value": 100000,
  "confidence": 0.95
}

For WEBSITE:
{
  "service": "website",
  "url": "google.com",
  "condition": "down",
  "confidence": 0.9
}

Rules:
For CRYPTO alerts:
1. crypto_id must be lowercase full name (bitcoin, ethereum, solana, etc.)
2. crypto_symbol must be uppercase ticker (BTC, ETH, SOL, etc.)
3. operator must be one of: above, below, equals
4. value must be a number (convert "100k" to 100000, "1m" to 1000000)
5. confidence between 0.0 and 1.0 (how confident you are in the parse)

For WEBSITE alerts:
1. url must be the website address (with or without http/https)
2. condition must be either "down" (default) or "up"
3. confidence between 0.0 and 1.0 (how confident you are in the parse)

General:
- Return ONLY the JSON, no explanation
- If input mentions monitoring/watching/alerting a website, use service: "website"
- If no specific condition mentioned for website, default to "down"

Examples:

CRYPTO Examples:
Input: "Bitcoin $100k"
Output: {"service": "crypto", "crypto_id": "bitcoin", "crypto_symbol": "BTC", "operator": "above", "value": 100000, "confidence": 0.95}

Input: "BTC drops below 50000"
Output: {"service": "crypto", "crypto_id": "bitcoin", "crypto_symbol": "BTC", "operator": "below", "value": 50000, "confidence": 0.9}

Input: "Ethereum reaches $5000"
Output: {"service": "crypto", "crypto_id": "ethereum", "crypto_symbol": "ETH", "operator": "equals", "value": 5000, "confidence": 0.9}

Input: "ETH price goes up 3000"
Output: {"service": "crypto", "crypto_id": "ethereum", "crypto_symbol": "ETH", "operator": "above", "value": 3000, "confidence": 0.9}

Input: "BTC price is 50000"
Output: {"service": "crypto", "crypto_id": "bitcoin", "crypto_symbol": "BTC", "operator": "equals", "value": 50000, "confidence": 0.85}

Input: "Solana below 100"
Output: {"service": "crypto", "crypto_id": "solana", "crypto_symbol": "SOL", "operator": "below", "value": 100, "confidence": 0.9}

WEBSITE Examples:
Input: "monitor google.com"
Output: {"service": "website", "url": "google.com", "condition": "down", "confidence": 0.9}

Input: "alert me when mysite.az is down"
Output: {"service": "website", "url": "mysite.az", "condition": "down", "confidence": 0.95}

Input: "notify when facebook.com goes up"
Output: {"service": "website", "url": "facebook.com", "condition": "up", "confidence": 0.9}

Input: "watch https://example.com"
Output: {"service": "website", "url": "example.com", "condition": "down", "confidence": 0.85}

Input: "tell me when myapp.com comes back online"
Output: {"service": "website", "url": "myapp.com", "condition": "up", "confidence": 0.9}

Input: "sayt.az işləsə mənə xəbər ver" (Azerbaijani: alert me when sayt.az works)
Output: {"service": "website", "url": "sayt.az", "condition": "up", "confidence": 0.9}

Input: "example.com işləməyəndə bildiriş göndər" (Azerbaijani: send notification when example.com doesn't work)
Output: {"service": "website", "url": "example.com", "condition": "down", "confidence": 0.9}

Input: "monitor edin google.com" (Azerbaijani: monitor google.com)
Output: {"service": "website", "url": "google.com", "condition": "down", "confidence": 0.85}

Common crypto symbols to recognize:
- BTC/Bitcoin → bitcoin
- ETH/Ethereum → ethereum
- ETC/Ethereum Classic → ethereum-classic
- SOL/Solana → solana
- ADA/Cardano → cardano
- DOT/Polkadot → polkadot
- MATIC/Polygon → matic-network

Now parse the user input above and return JSON:
PROMPT;
    }

    /**
     * Parse LLM response
     *
     * @param string $response
     * @return array
     */
    protected function parseResponse(string $response): array
    {
        // Extract JSON from response (LLM might add extra text)
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/', $response, $matches)) {
            $json = $matches[0];
        } else {
            throw new \Exception('No JSON found in LLM response');
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON in LLM response: ' . json_last_error_msg());
        }

        // Validate required fields based on service type
        $commonRequired = ['service', 'confidence'];

        // Check common fields
        foreach ($commonRequired as $field) {
            if (!isset($data[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        // Service-specific validation
        if ($data['service'] === 'crypto') {
            $cryptoRequired = ['operator', 'value'];
            foreach ($cryptoRequired as $field) {
                if (!isset($data[$field])) {
                    throw new \Exception("Missing required field for crypto: {$field}");
                }
            }
        } elseif ($data['service'] === 'website') {
            $websiteRequired = ['url', 'condition'];
            foreach ($websiteRequired as $field) {
                if (!isset($data[$field])) {
                    throw new \Exception("Missing required field for website: {$field}");
                }
            }
        }

        // Ensure confidence is a float
        $data['confidence'] = (float) $data['confidence'];

        return $data;
    }
}
