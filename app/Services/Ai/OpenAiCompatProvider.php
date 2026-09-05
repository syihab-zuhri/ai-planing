<?php

namespace App\Services\Ai;

/**
 * OpenAiCompatProvider — provider fallback (BR-GEN-003).
 *
 * Menargetkan endpoint OpenAI-compatible mana pun di luar 9router.
 * Konfigurasi via ENV:
 *   - OPENAI_COMPAT_BASE_URL
 *   - OPENAI_COMPAT_API_KEY
 *   - OPENAI_COMPAT_MODEL
 *   - OPENAI_COMPAT_TIMEOUT (detik, default 60)
 */
class OpenAiCompatProvider extends OpenAiChatProvider
{
    public function name(): string
    {
        return 'openai_compat';
    }
}
