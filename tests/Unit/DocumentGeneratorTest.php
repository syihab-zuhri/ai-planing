<?php

namespace Tests\Unit;

use App\Models\AiJob;
use App\Models\Project;
use App\Services\Ai\AiProviderException;
use App\Services\Ai\AiProviderResolver;
use App\Services\Ai\AiResponse;
use App\Services\Ai\DocumentGenerator;
use App\Services\Ai\MarkdownValidator;
use App\Services\Ai\MockAiProvider;
use App\Services\Ai\PromptBuilder;
use Tests\TestCase;

/**
 * DocumentGeneratorTest — retry provider-level, fallback (BR-GEN-003),
 * validasi output (BR-GEN-004), dan audit ai_jobs (BR-GEN-005).
 *
 * Catatan: Tests\TestCase sudah memakai RefreshDatabase + skema SQLite sendiri,
 * jadi trait tersebut TIDAK boleh di-use ulang di sini.
 */
class DocumentGeneratorTest extends TestCase
{
    private function project(): Project
    {
        return Project::create([
            'session_id'       => 'sess-docgen-' . uniqid(),
            'draft_state'      => [
                'intake' => ['project_name' => 'Proyek Uji', 'project_goal' => 'Tujuan uji.'],
                'documents' => [],
            ],
            'current_gate'     => 'A',
            'last_activity_at' => now(),
        ]);
    }

    private function generator(
        \App\Services\Ai\AiProviderInterface $primary,
        ?\App\Services\Ai\AiProviderInterface $fallback = null,
    ): DocumentGenerator {
        $resolver = new class($fallback) extends AiProviderResolver {
            public function __construct(private readonly ?\App\Services\Ai\AiProviderInterface $fb)
            {
                parent::__construct(app());
            }

            public function fallback(): ?\App\Services\Ai\AiProviderInterface
            {
                return $this->fb;
            }
        };

        return new DocumentGenerator(
            $primary,
            $resolver,
            new PromptBuilder(),
            new MarkdownValidator(200),
        );
    }

    private function validMarkdown(string $title = 'Judul'): string
    {
        return "# {$title}\n\n" . str_repeat('Isi dokumen yang memadai. ', 15);
    }

    public function test_successful_generation_persists_document_and_job(): void
    {
        $project = $this->project();
        $mock = new MockAiProvider();
        $mock->mockContent = $this->validMarkdown('PLANNING');

        $result = $this->generator($mock)->generate($project, 'PLANNING.md');

        $this->assertSame('done', $result['status']);
        $this->assertSame('mock', $result['provider']);
        $this->assertSame(1, $result['attempts']);

        $project->refresh();
        $this->assertArrayHasKey('PLANNING.md', $project->draft_state['documents']);
        $this->assertStringContainsString('# PLANNING', $project->draft_state['documents']['PLANNING.md']);

        $job = AiJob::where('project_id', $project->id)->firstOrFail();
        $this->assertSame('done', $job->status);
        $this->assertGreaterThan(0, $job->token_in);
        $this->assertNull($job->error_message);
    }

    public function test_wrapping_code_fence_is_stripped_before_persist(): void
    {
        $project = $this->project();
        $mock = new MockAiProvider();
        $mock->mockContent = "```markdown\n" . $this->validMarkdown('API') . "\n```";

        $this->generator($mock)->generate($project, 'API.md');

        $project->refresh();
        $stored = $project->draft_state['documents']['API.md'];

        $this->assertStringStartsWith('# API', $stored);
        $this->assertStringNotContainsString('```markdown', $stored);
    }

    public function test_short_output_is_retried_then_failed(): void
    {
        $project = $this->project();

        $provider = new class extends MockAiProvider {
            public int $calls = 0;

            public function chat(array $messages, array $options = []): AiResponse
            {
                $this->calls++;

                return new AiResponse('# Pendek', 10, 5, 0, 'mock');
            }
        };

        $result = $this->generator($provider)->generate($project, 'SRS.md');

        $this->assertSame('failed', $result['status']);
        // retry_on_malformed default true → 2 percobaan pada provider yang sama.
        $this->assertSame(2, $provider->calls);
        $this->assertStringContainsString('terlalu pendek', $result['error']);

        $project->refresh();
        $this->assertArrayNotHasKey('SRS.md', (array) ($project->draft_state['documents'] ?? []));

        $job = AiJob::where('project_id', $project->id)->firstOrFail();
        $this->assertSame('failed', $job->status);
    }

    public function test_second_attempt_success_is_recorded(): void
    {
        $project = $this->project();
        $valid = $this->validMarkdown('ERD');

        $provider = new class($valid) extends MockAiProvider {
            public int $calls = 0;

            public function __construct(private readonly string $good)
            {
            }

            public function chat(array $messages, array $options = []): AiResponse
            {
                $this->calls++;

                if ($this->calls === 1) {
                    return new AiResponse('tanpa heading', 10, 5, 0, 'mock');
                }

                return new AiResponse($this->good, 10, 5, 0, 'mock');
            }
        };

        $result = $this->generator($provider)->generate($project, 'ERD.md');

        $this->assertSame('done', $result['status']);
        $this->assertSame(2, $result['attempts']);
        $this->assertSame(2, $provider->calls);
    }

    public function test_retryable_provider_error_moves_to_fallback(): void
    {
        $project = $this->project();

        $failing = new class extends MockAiProvider {
            public int $calls = 0;

            public function chat(array $messages, array $options = []): AiResponse
            {
                $this->calls++;

                throw new AiProviderException('rate limited', 'PROVIDER_RATE_LIMITED', true);
            }

            public function name(): string
            {
                return 'ninerouter';
            }
        };

        $fallbackContent = $this->validMarkdown('SECURITY');
        $fallback = new class($fallbackContent) extends MockAiProvider {
            public function __construct(private readonly string $body)
            {
            }

            public function chat(array $messages, array $options = []): AiResponse
            {
                return new AiResponse($this->body, 100, 50, 12, 'openai_compat');
            }

            public function name(): string
            {
                return 'openai_compat';
            }
        };

        $result = $this->generator($failing, $fallback)->generate($project, 'SECURITY.md');

        $this->assertSame('done', $result['status']);
        $this->assertSame('openai_compat', $result['provider']);
        $this->assertSame(2, $failing->calls, 'primary dicoba 2x sebelum fallback');

        $job = AiJob::where('project_id', $project->id)->firstOrFail();
        $this->assertSame('openai_compat', $job->provider);
        $this->assertSame('done', $job->status);
    }

    public function test_non_retryable_error_stops_immediately(): void
    {
        $project = $this->project();

        $provider = new class extends MockAiProvider {
            public int $calls = 0;

            public function chat(array $messages, array $options = []): AiResponse
            {
                $this->calls++;

                throw new AiProviderException('kredensial ditolak', 'PROVIDER_REJECTED', false);
            }
        };

        $fallback = new MockAiProvider();
        $result = $this->generator($provider, $fallback)->generate($project, 'DSD.md');

        $this->assertSame('failed', $result['status']);
        $this->assertSame(1, $provider->calls, 'tidak boleh retry untuk error non-retryable');
        $this->assertStringContainsString('kredensial ditolak', $result['error']);
    }

    public function test_existing_job_row_is_reused(): void
    {
        $project = $this->project();
        $mock = new MockAiProvider();
        $mock->mockContent = $this->validMarkdown('TASKS');

        $job = AiJob::create([
            'project_id' => $project->id,
            'doc_id'     => 'TASKS.md',
            'provider'   => 'mock',
            'status'     => 'queued',
        ]);

        $this->generator($mock)->generate($project, 'TASKS.md', $job);

        $this->assertSame(1, AiJob::where('project_id', $project->id)->count());
        $this->assertSame('done', $job->refresh()->status);
    }

    public function test_generation_does_not_clobber_other_documents(): void
    {
        $project = $this->project();
        $state = $project->draft_state;
        $state['documents'] = ['API.md' => $this->validMarkdown('API')];
        $project->draft_state = $state;
        $project->save();

        $mock = new MockAiProvider();
        $mock->mockContent = $this->validMarkdown('ERD');

        $this->generator($mock)->generate($project, 'ERD.md');

        $project->refresh();
        $this->assertArrayHasKey('API.md', $project->draft_state['documents']);
        $this->assertArrayHasKey('ERD.md', $project->draft_state['documents']);
    }

    public function test_unexpected_exception_is_captured_as_failed(): void
    {
        $project = $this->project();

        $provider = new class extends MockAiProvider {
            public function chat(array $messages, array $options = []): AiResponse
            {
                throw new \RuntimeException('boom tak terduga');
            }
        };

        $result = $this->generator($provider)->generate($project, 'RUNBOOK.md');

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('boom tak terduga', $result['error']);
        $this->assertSame('failed', AiJob::where('project_id', $project->id)->value('status'));
    }
}
