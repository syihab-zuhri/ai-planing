<?php

namespace Tests\Unit;

use App\Services\Ai\AiResponse;
use PHPUnit\Framework\TestCase;

class AiResponseTest extends TestCase
{
    public function test_constructor_sets_all_fields(): void
    {
        $response = new AiResponse(
            content: 'Hello world',
            tokens_in: 10,
            tokens_out: 20,
            latency_ms: 150,
            provider: 'test-provider',
        );

        $this->assertEquals('Hello world', $response->content);
        $this->assertEquals(10, $response->tokens_in);
        $this->assertEquals(20, $response->tokens_out);
        $this->assertEquals(150, $response->latency_ms);
        $this->assertEquals('test-provider', $response->provider);
    }

    public function test_constructor_defaults(): void
    {
        $response = new AiResponse(content: 'test');

        $this->assertEquals('test', $response->content);
        $this->assertEquals(0, $response->tokens_in);
        $this->assertEquals(0, $response->tokens_out);
        $this->assertEquals(0, $response->latency_ms);
        $this->assertEquals('mock', $response->provider);
    }

    public function test_to_array_returns_all_fields(): void
    {
        $response = new AiResponse(
            content: '# Document',
            tokens_in: 50,
            tokens_out: 100,
            latency_ms: 250,
            provider: 'ninerouter',
        );

        $array = $response->toArray();

        $this->assertIsArray($array);
        $this->assertEquals([
            'content'    => '# Document',
            'tokens_in'  => 50,
            'tokens_out' => 100,
            'latency_ms' => 250,
            'provider'   => 'ninerouter',
        ], $array);
    }

    public function test_to_array_with_defaults(): void
    {
        $response = new AiResponse(content: 'minimal');
        $array = $response->toArray();

        $this->assertEquals('minimal', $array['content']);
        $this->assertEquals(0, $array['tokens_in']);
        $this->assertEquals(0, $array['tokens_out']);
        $this->assertEquals(0, $array['latency_ms']);
        $this->assertEquals('mock', $array['provider']);
    }
}
