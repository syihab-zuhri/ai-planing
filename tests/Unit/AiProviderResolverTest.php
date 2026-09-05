<?php

namespace Tests\Unit;

use App\Services\Ai\AiProviderResolver;
use App\Services\Ai\MockAiProvider;
use App\Services\Ai\NineRouterProvider;
use App\Services\Ai\OpenAiCompatProvider;
use Tests\TestCase;

/**
 * AiProviderResolverTest — pemilihan provider dari config/ai.php.
 */
class AiProviderResolverTest extends TestCase
{
    private function resolver(): AiProviderResolver
    {
        return $this->app->make(AiProviderResolver::class);
    }

    private function configureNineRouter(): void
    {
        config([
            'ai.providers.ninerouter.base_url' => 'http://127.0.0.1:20128/v1',
            'ai.providers.ninerouter.api_key'  => 'key',
            'ai.providers.ninerouter.model'    => 'model-x',
            'ai.providers.ninerouter.timeout'  => 45,
        ]);
    }

    private function configureOpenAiCompat(): void
    {
        config([
            'ai.providers.openai_compat.base_url' => 'https://api.example.test/v1',
            'ai.providers.openai_compat.api_key'  => 'key2',
            'ai.providers.openai_compat.model'    => 'gpt-test',
        ]);
    }

    public function test_primary_mock_by_default_in_tests(): void
    {
        $this->assertInstanceOf(MockAiProvider::class, $this->resolver()->primary());
    }

    public function test_primary_returns_ninerouter_when_configured(): void
    {
        $this->configureNineRouter();
        config(['ai.primary' => 'ninerouter']);

        $provider = $this->resolver()->primary();

        $this->assertInstanceOf(NineRouterProvider::class, $provider);
        $this->assertSame('ninerouter', $provider->name());
    }

    public function test_primary_falls_back_to_mock_when_config_incomplete(): void
    {
        config([
            'ai.primary' => 'ninerouter',
            'ai.providers.ninerouter.base_url' => 'http://127.0.0.1:20128/v1',
            'ai.providers.ninerouter.api_key'  => '',
            'ai.providers.ninerouter.model'    => '',
        ]);

        $this->assertInstanceOf(MockAiProvider::class, $this->resolver()->primary());
    }

    public function test_primary_falls_back_to_mock_for_unknown_provider(): void
    {
        config(['ai.primary' => 'does_not_exist']);

        $this->assertInstanceOf(MockAiProvider::class, $this->resolver()->primary());
    }

    public function test_fallback_null_when_same_as_primary(): void
    {
        config(['ai.primary' => 'mock', 'ai.fallback' => 'mock']);

        $this->assertNull($this->resolver()->fallback());
    }

    public function test_fallback_returns_openai_compat_when_configured(): void
    {
        $this->configureNineRouter();
        $this->configureOpenAiCompat();
        config(['ai.primary' => 'ninerouter', 'ai.fallback' => 'openai_compat']);

        $fallback = $this->resolver()->fallback();

        $this->assertInstanceOf(OpenAiCompatProvider::class, $fallback);
        $this->assertSame('openai_compat', $fallback->name());
    }

    public function test_fallback_null_when_incomplete(): void
    {
        $this->configureNineRouter();
        config([
            'ai.primary'  => 'ninerouter',
            'ai.fallback' => 'openai_compat',
            'ai.providers.openai_compat.base_url' => '',
            'ai.providers.openai_compat.api_key'  => '',
            'ai.providers.openai_compat.model'    => '',
        ]);

        $this->assertNull($this->resolver()->fallback());
    }

    public function test_make_returns_null_for_unknown_name(): void
    {
        $this->assertNull($this->resolver()->make('nope'));
    }

    public function test_make_mock_always_available(): void
    {
        $this->assertInstanceOf(MockAiProvider::class, $this->resolver()->make('mock'));
    }

    /**
     * Container binding AiProviderInterface harus melewati resolver, sehingga
     * mengganti config ai.primary cukup untuk menukar provider aktif.
     */
    public function test_container_binding_uses_resolver(): void
    {
        $this->configureNineRouter();
        config(['ai.primary' => 'ninerouter']);

        $provider = $this->app->make(\App\Services\Ai\AiProviderInterface::class);

        $this->assertSame('ninerouter', $provider->name());
    }
}
