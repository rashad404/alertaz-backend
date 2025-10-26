<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements AIProviderInterface
{
    protected $config;

    public function __construct()
    {
        $this->config = config('ai.providers.openai');
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
            throw new \Exception('No OpenAI API keys configured. Check OPENAI_API_KEYS or OPENAI_API_KEY in .env');
        }

        return $apiKeys[array_rand($apiKeys)];
    }

    public function generateText(string $prompt, $maxTokens = null): string
    {
        if (!$this->isAvailable()) {
            throw new \Exception('OpenAI provider is not properly configured. Check OPENAI_API_KEYS or OPENAI_API_KEY in .env');
        }

        try {
            $apiKey = $this->selectApiKey();
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
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
                Log::error('OpenAI API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('OpenAI API request failed: ' . $response->body());
            }

            $data = $response->json();

            if (!isset($data['choices'][0]['message']['content'])) {
                throw new \Exception('Unexpected response format from OpenAI API');
            }

            return $data['choices'][0]['message']['content'];

        } catch (\Exception $e) {
            Log::error('OpenAI API exception', [
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
        return 'openai';
    }
}
