<?php

namespace App\Services\Ai;

/**
 * NineRouterProvider — provider primary (ADR-001, ARCHITECTURE §1).
 *
 * Menargetkan instance 9router lokal (default http://127.0.0.1:20128/v1) yang
 * mengekspos API kompatibel OpenAI. Konfigurasi via ENV:
 *   - NINEROUTER_BASE_URL  (mis. http://127.0.0.1:20128/v1)
 *   - NINEROUTER_API_KEY
 *   - NINEROUTER_MODEL     (mis. dahono/qwen3.7-max)
 *   - NINEROUTER_TIMEOUT   (detik, default 60)
 */
class NineRouterProvider extends OpenAiChatProvider
{
    public function name(): string
    {
        return 'ninerouter';
    }
}
