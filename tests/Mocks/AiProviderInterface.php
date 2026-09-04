<?php

namespace Tests\Mocks;

/**
 * Local mirror of the AI provider interface defined in API.md §11.
 * Kept self-contained under tests/ so test code does not depend on
 * backend classes that may not exist yet. When the backend implements
 * the real `App\Services\Ai\AiProviderInterface`, the production code
 * can rely on this same shape and the MockAiProvider can implement
 * either contract interchangeably.
 */
interface AiProviderInterface
{
    /**
     * Send a chat completion request and return an AiResponse.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     */
    public function chat(array $messages, array $options = []): AiResponse;

    /**
     * Estimate the token count of a prompt string.
     */
    public function estimateTokens(string $prompt): int;
}