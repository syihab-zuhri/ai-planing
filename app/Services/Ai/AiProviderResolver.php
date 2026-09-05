<?php

namespace App\Services\Ai;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * AiProviderResolver — memilih implementasi provider berdasarkan config/ai.php.
 *
 * Aturan:
 *   - `ai.primary` menentukan provider utama. Jika konfigurasinya tidak lengkap
 *     (base_url/api_key/model kosong), resolver turun ke mock dan mencatat warning
 *     supaya pipeline tidak mati di produksi.
 *   - `ai.fallback` dipakai oleh GenerateDocumentJob bila primary gagal dengan
 *     error yang retryable (BR-GEN-003). Fallback diabaikan bila namanya sama
 *     dengan primary atau konfigurasinya tidak lengkap.
 */
class AiProviderResolver
{
    public function __construct(
        private readonly Container $container,
    ) {
    }

    /**
     * Provider utama. Selalu mengembalikan instance yang bisa dipakai.
     */
    public function primary(): AiProviderInterface
    {
        $name = (string) config('ai.primary', 'mock');
        $provider = $this->make($name);

        if ($provider === null) {
            if ($name !== 'mock') {
                Log::warning('ai_provider.fallback_to_mock', [
                    'requested' => $name,
                    'reason'    => 'konfigurasi provider tidak lengkap',
                ]);
            }

            return new MockAiProvider();
        }

        return $provider;
    }

    /**
     * Provider fallback, atau null jika tidak relevan/tidak dikonfigurasi.
     */
    public function fallback(): ?AiProviderInterface
    {
        $primaryName = (string) config('ai.primary', 'mock');
        $fallbackName = (string) config('ai.fallback', 'mock');

        if ($fallbackName === '' || $fallbackName === $primaryName) {
            return null;
        }

        return $this->make($fallbackName);
    }

    /**
     * Bangun provider berdasarkan nama. Null bila tidak dikenal atau
     * konfigurasinya belum lengkap.
     */
    public function make(string $name): ?AiProviderInterface
    {
        if ($name === 'mock') {
            return new MockAiProvider();
        }

        $config = (array) config("ai.providers.{$name}", []);

        if ($config === []) {
            return null;
        }

        $provider = match ($name) {
            'ninerouter'    => new NineRouterProvider(
                baseUrl: (string) ($config['base_url'] ?? ''),
                apiKey: (string) ($config['api_key'] ?? ''),
                model: (string) ($config['model'] ?? ''),
                timeoutSeconds: (int) ($config['timeout'] ?? 120),
                maxTokens: (int) config('ai.generation.max_tokens_out', 8000),
            ),
            'openai_compat' => new OpenAiCompatProvider(
                baseUrl: (string) ($config['base_url'] ?? ''),
                apiKey: (string) ($config['api_key'] ?? ''),
                model: (string) ($config['model'] ?? ''),
                timeoutSeconds: (int) ($config['timeout'] ?? 120),
                maxTokens: (int) config('ai.generation.max_tokens_out', 8000),
            ),
            default         => null,
        };

        if ($provider === null || !$provider->isConfigured()) {
            return null;
        }

        return $provider;
    }
}
