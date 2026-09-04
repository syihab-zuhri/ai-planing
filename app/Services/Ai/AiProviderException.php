<?php

namespace App\Services\Ai;

/**
 * Exception yang dilempar oleh implementasi AiProviderInterface.
 *
 * Pesan exception TIDAK BOLEH mengandung API key, payload prompt,
 * atau response body yang bersifat sensitif (lihat SECURITY.md TM-002
 * dan PRD/GENERATION.md §15).
 */
class AiProviderException extends \RuntimeException
{
    /**
     * Kode error generik untuk audit log / API response.
     * Contoh: PROVIDER_TIMEOUT, PROVIDER_RATE_LIMITED, PROVIDER_UNAVAILABLE.
     */
    public function __construct(
        string $message,
        public readonly string $errorCode = 'PROVIDER_UNAVAILABLE',
        public readonly bool $retryable = true,
    ) {
        parent::__construct($message);
    }
}