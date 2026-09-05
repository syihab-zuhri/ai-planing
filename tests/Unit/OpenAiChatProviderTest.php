<?php

namespace Tests\Unit;

use App\Services\Ai\AiProviderException;
use App\Services\Ai\NineRouterProvider;
use App\Services\Ai\OpenAiCompatProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * OpenAiChatProviderTest — perilaku NineRouterProvider / OpenAiCompatProvider
 * terhadap endpoint OpenAI-compatible, memakai Http::fake (tanpa jaringan).
 *
 * Acuan: API.md §11, PRD/GENERATION.md §14, SECURITY.md TM-002.
 */
class OpenAiChatProviderTest extends TestCase
{
    private function provider(int $timeout = 30): NineRouterProvider
    {
        return new NineRouterProvider(
            baseUrl: 'http://127.0.0.1:20128/v1',
            apiKey: 'secret-key-value',
            model: 'test-model',
            timeoutSeconds: $timeout,
            maxTokens: 1234,
        );
    }

    private function successBody(string $content = "# Judul\n\nIsi dokumen."): array
    {
        return [
            'id'      => 'chatcmpl-1',
            'model'   => 'test-model',
            'choices' => [
                ['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop'],
            ],
            'usage'   => ['prompt_tokens' => 120, 'completion_tokens' => 45, 'total_tokens' => 165],
        ];
    }

    public function test_name_identifies_provider(): void
    {
        $this->assertSame('ninerouter', $this->provider()->name());

        $fallback = new OpenAiCompatProvider('http://example.test/v1', 'k', 'm');
        $this->assertSame('openai_compat', $fallback->name());
    }

    public function test_is_configured_requires_url_key_and_model(): void
    {
        $this->assertTrue($this->provider()->isConfigured());
        $this->assertFalse((new NineRouterProvider('', 'k', 'm'))->isConfigured());
        $this->assertFalse((new NineRouterProvider('http://x/v1', '', 'm'))->isConfigured());
        $this->assertFalse((new NineRouterProvider('http://x/v1', 'k', ''))->isConfigured());
    }

    public function test_chat_returns_content_and_usage(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response($this->successBody(), 200),
        ]);

        $response = $this->provider()->chat([
            ['role' => 'system', 'content' => 'anchor'],
            ['role' => 'user', 'content' => 'buat dokumen'],
        ]);

        $this->assertSame("# Judul\n\nIsi dokumen.", $response->content);
        $this->assertSame(120, $response->tokens_in);
        $this->assertSame(45, $response->tokens_out);
        $this->assertSame('ninerouter', $response->provider);
        $this->assertGreaterThanOrEqual(0, $response->latency_ms);
    }

    public function test_request_sends_bearer_token_and_expected_payload(): void
    {
        Http::fake(['*/chat/completions' => Http::response($this->successBody(), 200)]);

        $this->provider()->chat([['role' => 'user', 'content' => 'halo']]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_ends_with($request->url(), '/v1/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer secret-key-value')
                && $body['model'] === 'test-model'
                && $body['max_tokens'] === 1234
                && $body['stream'] === false
                && $body['messages'] === [['role' => 'user', 'content' => 'halo']];
        });
    }

    public function test_options_override_model_and_max_tokens(): void
    {
        Http::fake(['*/chat/completions' => Http::response($this->successBody(), 200)]);

        $this->provider()->chat(
            [['role' => 'user', 'content' => 'halo']],
            ['model' => 'other-model', 'max_tokens' => 99, 'temperature' => 0.9],
        );

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['model'] === 'other-model'
                && $body['max_tokens'] === 99
                && $body['temperature'] === 0.9;
        });
    }

    public function test_messages_are_normalized(): void
    {
        Http::fake(['*/chat/completions' => Http::response($this->successBody(), 200)]);

        $this->provider()->chat([
            ['role' => 'weird', 'content' => 'jadi user'],
            ['role' => 'user', 'content' => ''],            // dibuang
            ['role' => 'assistant', 'content' => 'ok', 'extra' => 'dibuang'],
        ]);

        Http::assertSent(function ($request) {
            return $request->data()['messages'] === [
                ['role' => 'user', 'content' => 'jadi user'],
                ['role' => 'assistant', 'content' => 'ok'],
            ];
        });
    }

    public function test_rate_limit_maps_to_retryable_exception(): void
    {
        Http::fake(['*/chat/completions' => Http::response(['error' => 'slow down'], 429)]);

        try {
            $this->provider()->chat([['role' => 'user', 'content' => 'x']]);
            $this->fail('Expected AiProviderException');
        } catch (AiProviderException $e) {
            $this->assertSame('PROVIDER_RATE_LIMITED', $e->errorCode);
            $this->assertTrue($e->retryable);
        }
    }

    public function test_server_error_maps_to_retryable_unavailable(): void
    {
        Http::fake(['*/chat/completions' => Http::response('boom', 503)]);

        try {
            $this->provider()->chat([['role' => 'user', 'content' => 'x']]);
            $this->fail('Expected AiProviderException');
        } catch (AiProviderException $e) {
            $this->assertSame('PROVIDER_UNAVAILABLE', $e->errorCode);
            $this->assertTrue($e->retryable);
        }
    }

    public function test_auth_error_is_not_retryable(): void
    {
        Http::fake(['*/chat/completions' => Http::response(['error' => 'Missing API key'], 401)]);

        try {
            $this->provider()->chat([['role' => 'user', 'content' => 'x']]);
            $this->fail('Expected AiProviderException');
        } catch (AiProviderException $e) {
            $this->assertSame('PROVIDER_REJECTED', $e->errorCode);
            $this->assertFalse($e->retryable);
        }
    }

    public function test_client_error_is_not_retryable(): void
    {
        Http::fake(['*/chat/completions' => Http::response(['error' => 'bad model'], 400)]);

        try {
            $this->provider()->chat([['role' => 'user', 'content' => 'x']]);
            $this->fail('Expected AiProviderException');
        } catch (AiProviderException $e) {
            $this->assertSame('PROVIDER_REJECTED', $e->errorCode);
            $this->assertFalse($e->retryable);
        }
    }

    public function test_empty_content_maps_to_malformed(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '   ']]],
            ], 200),
        ]);

        try {
            $this->provider()->chat([['role' => 'user', 'content' => 'x']]);
            $this->fail('Expected AiProviderException');
        } catch (AiProviderException $e) {
            $this->assertSame('PROVIDER_MALFORMED', $e->errorCode);
            $this->assertTrue($e->retryable);
        }
    }

    public function test_missing_choices_maps_to_malformed(): void
    {
        Http::fake(['*/chat/completions' => Http::response(['object' => 'error'], 200)]);

        $this->expectException(AiProviderException::class);
        $this->provider()->chat([['role' => 'user', 'content' => 'x']]);
    }

    public function test_connection_exception_maps_to_timeout(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException(
                'cURL error 28: Operation timed out after 30000 ms (see http://127.0.0.1:20128/v1/chat/completions)'
            );
        });

        try {
            $this->provider(30)->chat([['role' => 'user', 'content' => 'x']]);
            $this->fail('Expected AiProviderException');
        } catch (AiProviderException $e) {
            $this->assertSame('PROVIDER_TIMEOUT', $e->errorCode);
            $this->assertTrue($e->retryable);
            $this->assertStringContainsString('30 detik', $e->getMessage());
        }
    }

    /**
     * SECURITY.md TM-002 — API key tidak boleh muncul di pesan exception apa pun.
     */
    public function test_exception_messages_never_leak_api_key(): void
    {
        foreach ([401, 429, 500, 400] as $status) {
            Http::fake(['*/chat/completions' => Http::response('err', $status)]);

            try {
                $this->provider()->chat([['role' => 'user', 'content' => 'x']]);
            } catch (AiProviderException $e) {
                $this->assertStringNotContainsString('secret-key-value', $e->getMessage());
            }
        }

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('failed with key secret-key-value in url');
        });

        try {
            $this->provider()->chat([['role' => 'user', 'content' => 'x']]);
        } catch (AiProviderException $e) {
            $this->assertStringNotContainsString('secret-key-value', $e->getMessage());
        }
    }

    public function test_usage_absent_falls_back_to_estimate(): void
    {
        $content = str_repeat('a', 400);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => $content]]],
            ], 200),
        ]);

        $response = $this->provider()->chat([['role' => 'user', 'content' => str_repeat('b', 40)]]);

        $this->assertSame(10, $response->tokens_in);   // 40 chars / 4
        $this->assertSame(100, $response->tokens_out); // 400 chars / 4
    }

    public function test_estimate_tokens_handles_string_and_messages(): void
    {
        $provider = $this->provider();

        $this->assertSame(5, $provider->estimateTokens('12345678901234567890'));
        $this->assertSame(3, $provider->estimateTokens([
            ['role' => 'system', 'content' => '1234'],
            ['role' => 'user', 'content' => '12345678'],
        ]));
        $this->assertSame(1, $provider->estimateTokens(''));
    }

    public function test_base_url_trailing_slash_is_handled(): void
    {
        Http::fake(['*/chat/completions' => Http::response($this->successBody(), 200)]);

        $provider = new NineRouterProvider('http://127.0.0.1:20128/v1/', 'k', 'm');
        $provider->chat([['role' => 'user', 'content' => 'x']]);

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:20128/v1/chat/completions');
    }
}
