<?php

namespace App\Services\Ai;

/**
 * AiProviderInterface — kontrak abstrak untuk semua AI provider.
 *
 * Implementasi konkret:
 *   - App\Services\Ai\MockAiProvider        (default, dev/test)
 *   - App\Services\Ai\NineRouterProvider    (primary production)
 *   - App\Services\Ai\OpenAiCompatProvider  (fallback)
 *
 * Definisi mengikuti API.md §11.
 *
 * Catatan keamanan (SECURITY.md TM-002):
 *   - Implementasi TIDAK BOLEH menulis/mengekspos API key di log, exception,
 *     atau return value manapun.
 *   - Token in/out dihitung dari usage API response atau estimator fallback.
 */
interface AiProviderInterface
{
    /**
     * Kirim prompt ke AI dan terima response.
     *
     * @param  array<int,array{role:string,content:string}>  $messages
     * @param  array<string,mixed>                          $options  temperature, max_tokens, dll.
     * @return AiResponse
     *
     * @throws \App\Services\Ai\AiProviderException  pada error provider / network
     */
    public function chat(array $messages, array $options = []): AiResponse;

    /**
     * Estimasi jumlah token untuk sebuah prompt (string atau array of messages).
     * Implementasi boleh memakai estimator sederhana (4 chars ~ 1 token).
     *
     * @param  string|array<int,array{role:string,content:string}>  $prompt
     */
    public function estimateTokens(string|array $prompt): int;

    /**
     * Identifier provider ("mock", "ninerouter", "openai_compat"). Dipakai untuk
     * audit log dan ai_jobs.provider.
     */
    public function name(): string;
}