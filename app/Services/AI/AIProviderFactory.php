<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;

class AIProviderFactory
{
    /**
     * Create an AI provider instance based on configuration
     *
     * @param string|null $provider Provider name (claude, openai, gemini) or null for default
     * @return AIProviderInterface
     * @throws \Exception If provider is unknown or not configured
     */
    public static function make(?string $provider = null): AIProviderInterface
    {
        $provider = $provider ?? config('ai.default_provider');

        return match($provider) {
            'claude' => new ClaudeProvider(),
            'openai' => new OpenAIProvider(),
            'gemini' => new GeminiProvider(),
            default => throw new \Exception("Unknown AI provider: {$provider}. Supported providers: claude, openai, gemini")
        };
    }

    /**
     * Get the default provider name from configuration
     *
     * @return string
     */
    public static function getDefaultProvider(): string
    {
        return config('ai.default_provider');
    }

    /**
     * Get all available provider names
     *
     * @return array
     */
    public static function getAvailableProviders(): array
    {
        return ['claude', 'openai', 'gemini'];
    }
}
