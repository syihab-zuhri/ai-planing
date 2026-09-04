<?php

namespace App\Services\Ai;

/**
 * Plain DTO untuk response AI.
 *
 * Field publik (sesuai API.md §11):
 *   - content     string  Isi response (Markdown/JSON/plain text).
 *   - tokens_in   int     Jumlah token input yang dikonsumsi.
 *   - tokens_out  int     Jumlah token output yang dihasilkan.
 *   - latency_ms  int     Latensi round-trip dalam milidetik.
 *   - provider    string  Identifier provider (lihat AiProviderInterface::name()).
 */
class AiResponse
{
    public function __construct(
        public string $content,
        public int $tokens_in = 0,
        public int $tokens_out = 0,
        public int $latency_ms = 0,
        public string $provider = 'mock',
    ) {
    }

    /**
     * Serialisasi aman ke JSON (semua field public → langsung encode).
     */
    public function toArray(): array
    {
        return [
            'content'    => $this->content,
            'tokens_in'  => $this->tokens_in,
            'tokens_out' => $this->tokens_out,
            'latency_ms' => $this->latency_ms,
            'provider'   => $this->provider,
        ];
    }
}