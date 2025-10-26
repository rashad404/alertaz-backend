<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeProvider implements AIProviderInterface
{
    protected $config;

    public function __construct()
    {
        $this->config = config('ai.providers.claude');
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
            throw new \Exception('No Claude API keys configured. Check ANTHROPIC_API_KEYS or ANTHROPIC_API_KEY in .env');
        }

        return $apiKeys[array_rand($apiKeys)];
    }

    public function generateText(string $prompt, $maxTokens = null): string
    {
        if (!$this->isAvailable()) {
            throw new \Exception('Claude provider is not properly configured. Check ANTHROPIC_API_KEYS or ANTHROPIC_API_KEY in .env');
        }

        try {
            $apiKey = $this->selectApiKey();
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => $this->config['api_version'],
                'content-type' => 'application/json',
            ])->post($this->config['api_url'], [
                'model' => $this->config['model'],
                'max_tokens' => $maxTokens ?? $this->config['max_tokens'],
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

            if (!$response->successful()) {
                Log::error('Claude API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Claude API request failed: ' . $response->body());
            }

            $data = $response->json();

            if (!isset($data['content'][0]['text'])) {
                throw new \Exception('Unexpected response format from Claude API');
            }

            return $data['content'][0]['text'];

        } catch (\Exception $e) {
            Log::error('Claude API exception', [
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
        return 'claude';
    }
}
