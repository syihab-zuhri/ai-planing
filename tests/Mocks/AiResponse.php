<?php

namespace Tests\Mocks;

/**
 * Local mirror of the AI provider response DTO defined in API.md §11.
 * Kept self-contained under tests/ so test code does not depend on
 * backend classes that may not exist yet.
 */
class AiResponse
{
    public string $content;
    public int $tokens_in;
    public int $tokens_out;
    public int $latency_ms;
    public string $provider;

    public function __construct(
        string $content = '',
        int $tokens_in = 0,
        int $tokens_out = 0,
        int $latency_ms = 0,
        string $provider = 'mock'
    ) {
        $this->content = $content;
        $this->tokens_in = $tokens_in;
        $this->tokens_out = $tokens_out;
        $this->latency_ms = $latency_ms;
        $this->provider = $provider;
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'tokens_in' => $this->tokens_in,
            'tokens_out' => $this->tokens_out,
            'latency_ms' => $this->latency_ms,
            'provider' => $this->provider,
        ];
    }
}