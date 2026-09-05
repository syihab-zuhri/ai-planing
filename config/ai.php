<?php

/**
 * Konfigurasi AI provider (ADR-001, ARCHITECTURE §1, PRD/GENERATION.md §7).
 *
 * Semua nilai dibaca dari ENV di sini — BUKAN via env() di dalam kode aplikasi —
 * supaya `php artisan config:cache` tetap aman (ENVIRONMENT.md §3).
 */
return [

    /*
     * Provider utama dan fallback. Nilai valid: 'ninerouter', 'openai_compat', 'mock'.
     * BR-GEN-003: jika primary gagal setelah 1 retry, pipeline pindah ke fallback.
     */
    'primary'  => env('AI_PROVIDER_PRIMARY', 'mock'),
    'fallback' => env('AI_PROVIDER_FALLBACK', 'mock'),

    'providers' => [

        'ninerouter' => [
            'base_url' => env('NINEROUTER_BASE_URL', 'http://127.0.0.1:20128/v1'),
            'api_key'  => env('NINEROUTER_API_KEY', ''),
            'model'    => env('NINEROUTER_MODEL', ''),
            'timeout'  => (int) env('NINEROUTER_TIMEOUT', 120),
        ],

        'openai_compat' => [
            'base_url' => env('OPENAI_COMPAT_BASE_URL', ''),
            'api_key'  => env('OPENAI_COMPAT_API_KEY', ''),
            'model'    => env('OPENAI_COMPAT_MODEL', ''),
            'timeout'  => (int) env('OPENAI_COMPAT_TIMEOUT', 120),
        ],

    ],

    'generation' => [
        /*
         * Mode eksekusi generate:
         *   - 'queue' (default): dispatch GenerateDocumentJob ke queue
         *     `blueprintforge`. WAJIB untuk provider sungguhan karena 23 dokumen
         *     jauh melewati proxy_read_timeout Nginx (60s).
         *   - 'sync' : generate inline dalam request. Untuk test & debugging.
         */
        'mode' => env('AI_GENERATION_MODE', 'queue'),

        // Batas hidup koneksi SSE watcher (detik).
        'stream_timeout_seconds' => (int) env('GEN_STREAM_TIMEOUT', 900),

        // OQ-GEN-002: batas token input per dokumen.
        'max_tokens_in'  => (int) env('GEN_MAX_TOKENS_IN', 16000),
        'max_tokens_out' => (int) env('GEN_MAX_TOKENS_OUT', 8000),

        // BR-GEN-004: retry 1x bila output gagal validasi Markdown.
        'retry_on_malformed' => (bool) env('GEN_RETRY_ON_MALFORMED', true),

        // BR-GEN-002: jeda antar dokumen untuk menghindari rate limit provider.
        'batch_delay_ms' => (int) env('GEN_BATCH_DELAY_MS', 500),

        // Panjang minimum dokumen yang dianggap valid (selaras ValidateController).
        'min_document_chars' => (int) env('GEN_MIN_DOCUMENT_CHARS', 200),
    ],

];
