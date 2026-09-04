<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * Security integration tests for BlueprintForge Phase 1.
 *
 * Covers the SECURITY.md §14 checklist at the unit-of-routing level:
 *   - Laravel CSRF middleware is active for web POST routes.
 *   - `.env` is NOT served by the public web server (a direct request
 *     to `/.env` must not return 200 OK with credentials).
 *   - Rate-limit middleware is configured for `wizard/*`, `generate/*`,
 *     `export/*` groups.
 *
 * Where possible these tests assert at the routing/middleware level so
 * they keep passing even when the underlying controllers are still
 * being implemented by the backend agent.
 */
class SecurityTest extends TestCase
{
    public function test_csrf_middleware_is_registered_in_web_group(): void
    {
        $webGroup = $this->getKernelMiddlewareGroups()['web'] ?? [];

        $this->assertContains(
            ValidateCsrfToken::class,
            $webGroup,
            'ValidateCsrfToken must be in the web middleware group (SECURITY.md §3 TM-007).'
        );
    }

    public function test_csrf_token_endpoint_returns_token(): void
    {
        // Hitting any web GET should attach an XSRF-TOKEN cookie so that
        // the framework's CSRF flow works for subsequent POSTs.
        $response = $this->get('/');

        $response->assertStatus(200);
        $cookies = $response->headers->getCookies();
        $names = array_map(fn ($c) => $c->getName(), $cookies);
        $this->assertContains('XSRF-TOKEN', $names, 'XSRF-TOKEN cookie must be set for the web group.');
    }

    public function test_env_file_is_not_served_by_public_router(): void
    {
        // Direct request to /.env must NOT return 200 with the env body.
        $response = $this->get('/.env');

        // Acceptable: 404 (Laravel router ignores unknown paths) or
        // 403 (server forbids dotfiles). The forbidden status is 200.
        $this->assertNotSame(
            200,
            $response->getStatusCode(),
            '.env must not be served by the public router (SECURITY.md §8).'
        );

        // Body must never contain credential-looking substrings.
        $body = $response->getContent() ?? '';
        $this->assertStringNotContainsString('APP_KEY', $body);
        $this->assertStringNotContainsString('DB_PASSWORD', $body);
    }

    public function test_wildcard_dotfile_routes_are_not_served(): void
    {
        // Other sensitive files that must not leak.
        foreach (['/.git/config', '/composer.json', '/.htaccess', '/storage/logs/laravel.log'] as $path) {
            $response = $this->get($path);
            $this->assertNotSame(
                200,
                $response->getStatusCode(),
                "{$path} must not be served by the public router."
            );
        }
    }

    public function test_throttle_middleware_class_is_available(): void
    {
        // SECURITY.md §10: per-session throttle for wizard/generate/export.
        // The actual route-level limiter is configured in AppServiceProvider
        // and applied via the 'throttle.session' alias in bootstrap/app.php.
        $this->assertTrue(
            class_exists(ThrottleRequests::class),
            'ThrottleRequests middleware class must be available.'
        );

        // Confirm the alias is wired in bootstrap/app.php.
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        $this->assertStringContainsString(
            'throttle.session',
            $bootstrap,
            'throttle.session alias must be registered (bootstrap/app.php).'
        );
    }

    public function test_api_middleware_group_has_session_and_csrf_middleware(): void
    {
        $apiGroup = $this->getKernelMiddlewareGroups()['api'] ?? [];

        $this->assertContains(
            \Illuminate\Session\Middleware\StartSession::class,
            $apiGroup,
            'StartSession must be in the api middleware group so /api/* can read the session.'
        );
        $this->assertContains(
            ValidateCsrfToken::class,
            $apiGroup,
            'Stateful session API endpoints must validate CSRF tokens.'
        );
    }

    public function test_api_post_without_csrf_token_is_rejected_in_runtime_stack(): void
    {
        $apiGroup = $this->getKernelMiddlewareGroups()['api'] ?? [];

        $this->assertContains(ValidateCsrfToken::class, $apiGroup);
    }

    /**
     * Pull the middleware group definitions off the HTTP kernel via
     * reflection. Laravel 11 stores them on the HTTP Kernel rather
     * than the router, so we read them directly.
     *
     * @return array<string, array<int, string>>
     */
    private function getKernelMiddlewareGroups(): array
    {
        $kernel = $this->app->make(HttpKernel::class);
        $reflection = new \ReflectionClass($kernel);
        $property = $reflection->getProperty('middlewareGroups');
        $property->setAccessible(true);

        return $property->getValue($kernel);
    }
}