<?php

namespace App\Contracts;

interface AIProviderInterface
{
    /**
     * Generate text from the AI provider
     *
     * @param string $prompt The prompt to send to the AI
     * @return string The generated text response
     * @throws \Exception When API call fails
     */
    public function generateText(string $prompt): string;

    /**
     * Check if the provider is properly configured and available
     *
     * @return bool True if provider is available
     */
    public function isAvailable(): bool;

    /**
     * Get the provider name
     *
     * @return string Provider name (claude, openai, gemini)
     */
    public function getProviderName(): string;
}
