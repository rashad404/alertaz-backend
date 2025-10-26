<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AIProviderInterface
{
    protected $config;

    public function __construct()
    {
        $this->config = config('ai.providers.gemini');
    }

    /**
     * Select an API key randomly for load balancing
     *
     * @return string
     * @throws \Exception
     */
    protected function selectApiKey(): string
    {
        $apiKeys = $this->config['api_keys'] ?? [];

        if (empty($apiKeys)) {
            throw new \Exception('No Gemini API keys configured. Check GEMINI_API_KEYS or GEMINI_API_KEY in .env');
        }

        return $apiKeys[array_rand($apiKeys)];
    }

    public function generateText(string $prompt, $maxTokens = null): string
    {
        if (!$this->isAvailable()) {
            throw new \Exception('Gemini provider is not properly configured. Check GEMINI_API_KEYS or GEMINI_API_KEY in .env');
        }

        try {
            $apiKey = $this->selectApiKey();
            $url = $this->config['api_url'] . '/' . $this->config['model'] . ':generateContent?key=' . $apiKey;

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => $maxTokens ?? $this->config['max_tokens'],
                ]
            ]);

            if (!$response->successful()) {
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Gemini API request failed: ' . $response->body());
            }

            $data = $response->json();

            // Log the full response for debugging
            Log::info('Gemini API full response', ['data' => $data]);

            if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                // Check if content was blocked by safety filters
                if (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] === 'SAFETY') {
                    throw new \Exception('Content was blocked by Gemini safety filters. Try rephrasing your input.');
                }

                // Check for other finish reasons
                if (isset($data['candidates'][0]['finishReason'])) {
                    throw new \Exception('Gemini API finished with reason: ' . $data['candidates'][0]['finishReason']);
                }

                // Log and throw generic error
                Log::error('Gemini unexpected response structure', ['data' => $data]);
                throw new \Exception('Unexpected response format from Gemini API');
            }

            return $data['candidates'][0]['content']['parts'][0]['text'];

        } catch (\Exception $e) {
            Log::error('Gemini API exception', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function isAvailable(): bool
    {
        return !empty($this->config['api_keys']) &&
               !empty($this->config['model']) &&
               !empty($this->config['api_url']);
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }
}
