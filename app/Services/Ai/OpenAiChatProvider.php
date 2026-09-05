<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAiChatProvider — implementasi AiProviderInterface untuk endpoint apa pun
 * yang kompatibel dengan skema OpenAI `POST /chat/completions`.
 *
 * Dipakai oleh dua provider konkret (lihat subclass di bawah file ini):
 *   - NineRouterProvider   → 9router lokal (primary, ENV NINEROUTER_*)
 *   - OpenAiCompatProvider → endpoint OpenAI-compatible lain (fallback, ENV OPENAI_COMPAT_*)
 *
 * Kontrak keamanan (SECURITY.md TM-002, PRD/GENERATION.md §15):
 *   - API key HANYA dikirim sebagai header Authorization; tidak pernah masuk
 *     message exception, log, maupun AiResponse.
 *   - Response body tidak pernah di-log mentah; hanya metadata (status, ukuran).
 *
 * Perilaku error (PRD/GENERATION.md §14):
 *   - Timeout / koneksi gagal → PROVIDER_TIMEOUT, retryable.
 *   - HTTP 429               → PROVIDER_RATE_LIMITED, retryable.
 *   - HTTP 5xx               → PROVIDER_UNAVAILABLE, retryable.
 *   - HTTP 4xx lain          → PROVIDER_REJECTED, tidak retryable.
 *   - Body tanpa choices[0].message.content → PROVIDER_MALFORMED, retryable.
 */
abstract class OpenAiChatProvider implements AiProviderInterface
{
    /** Estimator token sederhana (4 karakter ≈ 1 token). */
    private const CHARS_PER_TOKEN = 4;

    public function __construct(
        protected readonly string $baseUrl,
        protected readonly string $apiKey,
        protected readonly string $model,
        protected readonly int $timeoutSeconds = 60,
        protected readonly int $maxTokens = 8000,
    ) {
    }

    /**
     * Apakah provider punya konfigurasi minimum untuk dipakai.
     * Dipakai oleh AiProviderResolver agar tidak memilih provider setengah jadi.
     */
    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '' && $this->model !== '';
    }

    public function chat(array $messages, array $options = []): AiResponse
    {
        $payload = [
            'model'       => $options['model'] ?? $this->model,
            'messages'    => $this->normalizeMessages($messages),
            'max_tokens'  => (int) ($options['max_tokens'] ?? $this->maxTokens),
            'temperature' => (float) ($options['temperature'] ?? 0.4),
            'stream'      => false,
        ];

        $startedAt = microtime(true);

        try {
            $response = $this->request()->post('/chat/completions', $payload);
        } catch (ConnectionException $e) {
            // Pesan Guzzle bisa memuat URL lengkap; jangan diteruskan apa adanya.
            throw new AiProviderException(
                "Provider {$this->name()} tidak merespons dalam {$this->timeoutSeconds} detik.",
                'PROVIDER_TIMEOUT',
                true,
            );
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            throw $this->exceptionForStatus($response->status());
        }

        $body = $response->json();

        if (!is_array($body)) {
            throw new AiProviderException(
                "Provider {$this->name()} mengembalikan body non-JSON.",
                'PROVIDER_MALFORMED',
                true,
            );
        }

        $content = $body['choices'][0]['message']['content'] ?? null;

        if (!is_string($content) || trim($content) === '') {
            throw new AiProviderException(
                "Provider {$this->name()} mengembalikan response tanpa konten.",
                'PROVIDER_MALFORMED',
                true,
            );
        }

        $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];

        Log::info('ai_call.completed', [
            'provider'   => $this->name(),
            'model'      => (string) ($body['model'] ?? $payload['model']),
            'tokens_in'  => (int) ($usage['prompt_tokens'] ?? 0),
            'tokens_out' => (int) ($usage['completion_tokens'] ?? 0),
            'latency_ms' => $latencyMs,
        ]);

        return new AiResponse(
            content: $content,
            tokens_in: (int) ($usage['prompt_tokens'] ?? $this->estimateTokens($messages)),
            tokens_out: (int) ($usage['completion_tokens'] ?? $this->charsToTokens(mb_strlen($content))),
            latency_ms: $latencyMs,
            provider: $this->name(),
        );
    }

    public function estimateTokens(string|array $prompt): int
    {
        if (is_string($prompt)) {
            return $this->charsToTokens(mb_strlen($prompt));
        }

        $chars = 0;
        foreach ($prompt as $message) {
            $chars += mb_strlen((string) ($message['content'] ?? ''));
        }

        return $this->charsToTokens($chars);
    }

    /**
     * Konfigurasi HTTP client. Base URL dinormalisasi supaya `/chat/completions`
     * selalu menempel dengan benar (baik base diberi trailing slash atau tidak).
     */
    protected function request(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeoutSeconds)
            ->connectTimeout(min(10, $this->timeoutSeconds));
    }

    /**
     * Buang key selain role/content dan pastikan keduanya string —
     * mencegah payload tak terduga bocor ke provider.
     *
     * @param  array<int,array<string,mixed>>  $messages
     * @return array<int,array{role:string,content:string}>
     */
    protected function normalizeMessages(array $messages): array
    {
        $normalized = [];

        foreach ($messages as $message) {
            $role = (string) ($message['role'] ?? 'user');
            $content = (string) ($message['content'] ?? '');

            if ($content === '') {
                continue;
            }

            $normalized[] = [
                'role'    => in_array($role, ['system', 'user', 'assistant'], true) ? $role : 'user',
                'content' => $content,
            ];
        }

        return $normalized;
    }

    protected function exceptionForStatus(int $status): AiProviderException
    {
        return match (true) {
            $status === 429 => new AiProviderException(
                "Provider {$this->name()} menolak karena rate limit (HTTP 429).",
                'PROVIDER_RATE_LIMITED',
                true,
            ),
            $status === 401 || $status === 403 => new AiProviderException(
                "Provider {$this->name()} menolak kredensial (HTTP {$status}).",
                'PROVIDER_REJECTED',
                false,
            ),
            $status >= 500 => new AiProviderException(
                "Provider {$this->name()} mengalami error server (HTTP {$status}).",
                'PROVIDER_UNAVAILABLE',
                true,
            ),
            default => new AiProviderException(
                "Provider {$this->name()} menolak permintaan (HTTP {$status}).",
                'PROVIDER_REJECTED',
                false,
            ),
        };
    }

    protected function charsToTokens(int $chars): int
    {
        return (int) max(1, ceil($chars / self::CHARS_PER_TOKEN));
    }
}
