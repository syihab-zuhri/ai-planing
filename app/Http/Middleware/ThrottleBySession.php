<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * ThrottleBySession — rate limiter per session_id dengan key berbeda
 * untuk kategori endpoint (wizard/generate/export).
 *
 * Acuan: API.md §7 + SECURITY.md §10.
 *
 * Rate default (override via env RATE_LIMIT_*):
 *   - wizard/*   → 30/min
 *   - generate/* → 10/min
 *   - export/*   →  5/min
 *
 * Identifier sesi: kalau session tersedia, pakai session()->getId().
 * Fallback ke IP bila session belum di-start (mis. middleware ini dipanggil
 * terlalu awal — biasanya tidak terjadi karena session middleware
 * di Laravel 11 default berada di global middleware group).
 */
class ThrottleBySession
{
    /**
     * Handle request. Parameter:
     *   - $category string  salah satu: 'wizard' | 'generate' | 'export'
     */
    public function handle(Request $request, Closure $next, string $category = 'wizard'): Response
    {
        $limit = $this->limitFor($category);
        $key = $this->key($request, $category);

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return $this->tooMany($key, $limit);
        }

        RateLimiter::hit($key, 60); // decay 60 detik (1 menit)

        $response = $next($request);

        // Tambahkan header X-RateLimit-* agar client tahu sisa quota.
        $remaining = RateLimiter::remaining($key, $limit);
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $remaining));

        return $response;
    }

    private function limitFor(string $category): int
    {
        return match ($category) {
            'generate' => (int) env('RATE_LIMIT_GENERATE', 10),
            'export'   => (int) env('RATE_LIMIT_EXPORT', 5),
            default    => (int) env('RATE_LIMIT_WIZARD', 30),
        };
    }

    private function key(Request $request, string $category): string
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $identifier = $sessionId ?: ('ip:' . $request->ip());

        return "ratelimit:{$category}:{$identifier}";
    }

    private function tooMany(string $key, int $limit): Response
    {
        $retryAfter = RateLimiter::availableIn($key);

        return response()->json([
            'error' => [
                'code'    => 'RATE_LIMITED',
                'message' => 'Terlalu banyak permintaan, coba lagi nanti.',
                'details' => [
                    'limit_per_minute' => $limit,
                    'retry_after'      => $retryAfter,
                ],
            ],
        ], 429, [
            'Retry-After' => (string) $retryAfter,
            'X-RateLimit-Limit' => (string) $limit,
            'X-RateLimit-Remaining' => '0',
        ]);
    }
}