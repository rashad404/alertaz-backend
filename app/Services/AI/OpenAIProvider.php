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

    public function generateText(string $prompt): string
    {
        if (!$this->isAvailable()) {
            throw new \Exception('OpenAI provider is not properly configured. Check OPENAI_API_KEY in .env');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'Content-Type' => 'application/json',
            ])->post($this->config['api_url'], [
                'model' => $this->config['model'],
                'max_tokens' => $this->config['max_tokens'],
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
        return !empty($this->config['api_key']) &&
               !empty($this->config['model']) &&
               !empty($this->config['api_url']);
    }

    public function getProviderName(): string
    {
        return 'openai';
    }
}
