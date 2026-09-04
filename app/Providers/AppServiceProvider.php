<?php

namespace App\Providers;

use App\Logging\SecretRedactionProcessor;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\MockAiProvider;
use App\Services\InputSanitizer;
use App\Services\PromptInjectionDetector;
use App\Services\WizardService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Default AI provider: mock. Tukar ke NineRouterProvider setelah
        // OQ-001 resolved (lihat ENV var AI_PROVIDER_PRIMARY).
        $this->app->bind(AiProviderInterface::class, function ($app) {
            return new MockAiProvider();
        });

        // Sanitizer & detector stateless — singleton cukup.
        $this->app->singleton(InputSanitizer::class);
        $this->app->singleton(PromptInjectionDetector::class);

        // WizardService — pull dependencies via container.
        $this->app->singleton(WizardService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Daftarkan SecretRedactionProcessor ke Monolog global
        // sehingga setiap log melewati filter (SECURITY.md §3 TM-002).
        $this->app->make('log')->pushProcessor(
            app(SecretRedactionProcessor::class)
        );
    }
}
