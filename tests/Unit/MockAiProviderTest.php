<?php

namespace Tests\Unit;

use App\Services\Ai\AiResponse;
use App\Services\Ai\MockAiProvider;
use PHPUnit\Framework\TestCase;

class MockAiProviderTest extends TestCase
{
    public function test_name_returns_mock(): void
    {
        $provider = new MockAiProvider();
        $this->assertEquals('mock', $provider->name());
    }

    public function test_chat_returns_ai_response(): void
    {
        $provider = new MockAiProvider();
        $messages = [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'Hello'],
        ];

        $response = $provider->chat($messages);

        $this->assertInstanceOf(AiResponse::class, $response);
        $this->assertNotEmpty($response->content);
        $this->assertGreaterThan(0, $response->tokens_in);
        $this->assertGreaterThan(0, $response->tokens_out);
        $this->assertEquals('mock', $response->provider);
    }

    public function test_chat_uses_custom_mock_content(): void
    {
        $provider = new MockAiProvider();
        $provider->mockContent = '# Custom Response';

        $response = $provider->chat([
            ['role' => 'user', 'content' => 'test'],
        ]);

        $this->assertEquals('# Custom Response', $response->content);
    }

    public function test_chat_with_simulated_latency(): void
    {
        $provider = new MockAiProvider();
        $provider->simulatedLatencyMs = 50;

        $start = microtime(true);
        $response = $provider->chat([
            ['role' => 'user', 'content' => 'test'],
        ]);
        $elapsed = (microtime(true) - $start) * 1000;

        $this->assertGreaterThanOrEqual(40, $elapsed);
        $this->assertEquals(50, $response->latency_ms);
    }

    public function test_estimate_tokens_with_string(): void
    {
        $provider = new MockAiProvider();
        // 20 chars → ceil(20/4) = 5 tokens
        $tokens = $provider->estimateTokens('12345678901234567890');
        $this->assertEquals(5, $tokens);
    }

    public function test_estimate_tokens_with_messages_array(): void
    {
        $provider = new MockAiProvider();
        $messages = [
            ['role' => 'system', 'content' => '1234'],  // 4 chars → 1 token
            ['role' => 'user', 'content' => '12345678'],  // 8 chars → 2 tokens
        ];
        // Total 12 chars → ceil(12/4) = 3 tokens
        $tokens = $provider->estimateTokens($messages);
        $this->assertEquals(3, $tokens);
    }

    public function test_estimate_tokens_minimum_is_1(): void
    {
        $provider = new MockAiProvider();
        // Empty string → 0 chars → max(1, ceil(0/4)) = 1
        $tokens = $provider->estimateTokens('');
        $this->assertEquals(1, $tokens);
    }

    public function test_chat_with_empty_messages(): void
    {
        $provider = new MockAiProvider();
        $response = $provider->chat([]);

        $this->assertInstanceOf(AiResponse::class, $response);
        // 0 chars input → tokens_in = 1 (minimum)
        $this->assertEquals(1, $response->tokens_in);
    }

    public function test_chat_options_are_accepted(): void
    {
        $provider = new MockAiProvider();
        // Options are ignored by mock but should not throw
        $response = $provider->chat(
            [['role' => 'user', 'content' => 'test']],
            ['temperature' => 0.7, 'max_tokens' => 1000]
        );

        $this->assertInstanceOf(AiResponse::class, $response);
    }
}
