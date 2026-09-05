<?php

namespace App\Providers;

use App\Logging\SecretRedactionProcessor;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\AiProviderResolver;
use App\Services\Ai\DocumentGenerator;
use App\Services\Ai\MarkdownValidator;
use App\Services\Ai\PromptBuilder;
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
        $this->app->singleton(AiProviderResolver::class);

        // Provider aktif ditentukan config/ai.php (ENV AI_PROVIDER_PRIMARY).
        // Resolver otomatis turun ke MockAiProvider bila konfigurasi provider
        // sungguhan belum lengkap, sehingga pipeline tidak pernah mati total.
        $this->app->bind(AiProviderInterface::class, function ($app) {
            return $app->make(AiProviderResolver::class)->primary();
        });

        $this->app->singleton(PromptBuilder::class);

        $this->app->singleton(MarkdownValidator::class, function ($app) {
            return new MarkdownValidator(
                (int) config('ai.generation.min_document_chars', 200)
            );
        });

        $this->app->bind(DocumentGenerator::class, function ($app) {
            return new DocumentGenerator(
                $app->make(AiProviderInterface::class),
                $app->make(AiProviderResolver::class),
                $app->make(PromptBuilder::class),
                $app->make(MarkdownValidator::class),
            );
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

        if ($this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
