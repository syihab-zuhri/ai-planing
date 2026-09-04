<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleBySession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ThrottleBySessionTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('ratelimit:wizard:' . session()->getId());
        RateLimiter::clear('ratelimit:generate:' . session()->getId());
        RateLimiter::clear('ratelimit:export:' . session()->getId());
    }

    public function test_wizard_requests_pass_under_limit(): void
    {
        $response = $this->withSession([])->postJson('/api/wizard/start');
        $response->assertSuccessful();
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    public function test_rate_limit_headers_are_set(): void
    {
        $response = $this->withSession([])->postJson('/api/wizard/start');
        $response->assertSuccessful();

        $this->assertEquals('30', $response->headers->get('X-RateLimit-Limit'));
        $remaining = (int) $response->headers->get('X-RateLimit-Remaining');
        $this->assertLessThanOrEqual(30, $remaining);
    }

    public function test_generate_limit_is_10(): void
    {
        // First do wizard start to have a project
        $this->withSession([])->postJson('/api/wizard/start');

        // Hit generate endpoint (will fail validation but throttle still applies)
        $response = $this->postJson('/api/generate/start');
        // 422 because no intake, but rate limit header should be 10
        $this->assertEquals('10', $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_export_limit_is_5(): void
    {
        $response = $this->withSession([])->postJson('/api/export/start');
        // 404 because no project, but rate limit header should be 5
        $this->assertEquals('5', $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_too_many_requests_returns_429(): void
    {
        // Simulate exhausting the export limit (5 per minute)
        $sessionId = session()->getId();
        $key = "ratelimit:export:{$sessionId}";

        // Pre-fill the limiter to its max
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($key, 60);
        }

        $response = $this->withSession([])->postJson('/api/export/start');
        $response->assertStatus(429)
                 ->assertJsonPath('error.code', 'RATE_LIMITED')
                 ->assertHeader('Retry-After')
                 ->assertHeader('X-RateLimit-Remaining', '0');
    }

    public function test_middleware_uses_session_id_when_available(): void
    {
        // Test through actual request — session should be used
        $middleware = new ThrottleBySession();

        $request = Request::create('/api/wizard/start', 'POST');
        $request->setLaravelSession(app('session.store'));
        $request->session()->start();

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['ok' => true]);
        }, 'wizard');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('30', $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_middleware_falls_back_to_ip_without_session(): void
    {
        $middleware = new ThrottleBySession();

        // Create request without session
        $request = Request::create('/api/test', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');

        $response = $middleware->handle($request, function ($req) {
            return response()->json(['ok' => true]);
        }, 'generate');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('10', $response->headers->get('X-RateLimit-Limit'));
    }

    public function test_different_categories_have_separate_buckets(): void
    {
        $middleware = new ThrottleBySession();

        $request = Request::create('/api/test', 'POST');
        $request->setLaravelSession(app('session.store'));
        $request->session()->start();

        // Hit export 5 times (its limit)
        for ($i = 0; $i < 5; $i++) {
            $middleware->handle($request, fn ($req) => response()->json([]), 'export');
        }

        // Export should now be rate limited
        $exportResp = $middleware->handle($request, fn ($req) => response()->json([]), 'export');
        $this->assertEquals(429, $exportResp->getStatusCode());

        // But wizard should still work (different bucket)
        $wizardResp = $middleware->handle($request, fn ($req) => response()->json([]), 'wizard');
        $this->assertEquals(200, $wizardResp->getStatusCode());
    }
}
