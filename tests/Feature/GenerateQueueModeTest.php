<?php

namespace Tests\Feature;

use App\Http\Controllers\GenerateController;
use App\Jobs\GenerateDocumentJob;
use App\Models\AiJob;
use App\Models\Project;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\MockAiProvider;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * GenerateQueueModeTest — mode 'queue' (default produksi).
 *
 * Memastikan:
 *   - /api/generate/start men-dispatch satu GenerateDocumentJob per dokumen
 *     dan kembali 202 tanpa memanggil AI di dalam request.
 *   - ai_jobs dibuat berstatus 'queued'.
 *   - Job yang dieksekusi menghasilkan dokumen dan menaikkan Gate B saat lengkap.
 *   - /api/generate/status memberi rekap progres untuk polling UI.
 */
class GenerateQueueModeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.generation.mode' => 'queue']);

        $mock = new MockAiProvider();
        $mock->mockContent = "# Dokumen Uji\n\n" . str_repeat('Isi dokumen yang memadai. ', 15);
        $this->app->instance(AiProviderInterface::class, $mock);
    }

    public function test_start_dispatches_one_job_per_document(): void
    {
        Queue::fake();
        $this->seedMinimalProject();

        $response = $this->postJson('/api/generate/start')->assertStatus(202);

        $response->assertJsonPath('mode', 'queue');
        $response->assertJsonPath('total', count(GenerateController::DOCUMENT_IDS));
        $response->assertJsonPath('results.0.status', 'queued');

        Queue::assertPushed(GenerateDocumentJob::class, count(GenerateController::DOCUMENT_IDS));

        $project = Project::first();
        $this->assertSame(
            count(GenerateController::DOCUMENT_IDS),
            AiJob::where('project_id', $project->id)->where('status', 'queued')->count()
        );

        // Tidak ada dokumen yang dihasilkan di dalam request.
        $project->refresh();
        $this->assertEmpty((array) ($project->draft_state['documents'] ?? []));
    }

    public function test_dispatched_job_targets_blueprintforge_queue(): void
    {
        Queue::fake();
        $this->seedMinimalProject();

        $this->postJson('/api/generate/start')->assertStatus(202);

        Queue::assertPushed(GenerateDocumentJob::class, function (GenerateDocumentJob $job) {
            return $job->queue === 'blueprintforge';
        });
    }

    public function test_executing_all_jobs_produces_documents_and_gate_b(): void
    {
        $this->seedMinimalProject();

        // Queue sync (phpunit.xml QUEUE_CONNECTION=sync) → job jalan seketika.
        $this->postJson('/api/generate/start')->assertStatus(202);

        $project = Project::first()->refresh();
        $documents = (array) ($project->draft_state['documents'] ?? []);

        $this->assertCount(count(GenerateController::DOCUMENT_IDS), $documents);
        foreach (GenerateController::DOCUMENT_IDS as $docId) {
            $this->assertArrayHasKey($docId, $documents);
            $this->assertGreaterThan(200, mb_strlen($documents[$docId]));
        }

        $this->assertContains($project->current_gate, ['B', 'C', 'D']);
        $this->assertSame(
            0,
            AiJob::where('project_id', $project->id)->where('status', '!=', 'done')->count()
        );
    }

    public function test_status_endpoint_reports_progress(): void
    {
        $this->seedMinimalProject();
        $this->postJson('/api/generate/start')->assertStatus(202);

        $response = $this->getJson('/api/generate/status')->assertOk();

        $response->assertJsonPath('mode', 'queue');
        $response->assertJsonPath('total', count(GenerateController::DOCUMENT_IDS));
        $response->assertJsonPath('counts.done', count(GenerateController::DOCUMENT_IDS));
        $response->assertJsonPath('finished', true);

        $docs = $response->json('documents');
        $this->assertCount(count(GenerateController::DOCUMENT_IDS), $docs);
        $this->assertGreaterThan(200, $docs[0]['chars']);
    }

    public function test_status_without_project_returns_404(): void
    {
        $this->getJson('/api/generate/status')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_status_reports_pending_before_start(): void
    {
        $this->seedMinimalProject();

        $response = $this->getJson('/api/generate/status')->assertOk();

        $response->assertJsonPath('counts.pending', count(GenerateController::DOCUMENT_IDS));
        $response->assertJsonPath('finished', false);
    }

    public function test_retry_in_queue_mode_returns_202_and_queues_job(): void
    {
        Queue::fake();
        $this->seedMinimalProject();

        $response = $this->postJson('/api/generate/retry/PLANNING.md')->assertStatus(202);

        $response->assertJsonPath('doc_id', 'PLANNING.md');
        $response->assertJsonPath('status', 'queued');

        Queue::assertPushed(GenerateDocumentJob::class, 1);
    }

    public function test_start_without_intake_still_returns_422(): void
    {
        Queue::fake();

        $this->postJson('/api/generate/start')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        Queue::assertNothingPushed();
    }

    public function test_cancel_marks_queued_jobs_cancelled(): void
    {
        Queue::fake();
        $this->seedMinimalProject();
        $this->postJson('/api/generate/start')->assertStatus(202);

        $this->postJson('/api/generate/cancel')->assertOk()->assertJsonPath('cancelled', true);

        $project = Project::first();
        $this->assertSame(
            0,
            AiJob::where('project_id', $project->id)->whereIn('status', ['queued', 'running'])->count()
        );
        $this->assertGreaterThan(
            0,
            AiJob::where('project_id', $project->id)->where('status', 'cancelled')->count()
        );
    }

    /**
     * Job yang ai_job-nya sudah 'done' tidak boleh memanggil provider lagi.
     */
    public function test_job_skips_when_ai_job_already_done(): void
    {
        $this->seedMinimalProject();
        $project = Project::first();

        $counting = new class extends MockAiProvider {
            public int $calls = 0;

            public function chat(array $messages, array $options = []): \App\Services\Ai\AiResponse
            {
                $this->calls++;

                return parent::chat($messages, $options);
            }
        };
        $this->app->instance(AiProviderInterface::class, $counting);

        $aiJob = AiJob::create([
            'project_id' => $project->id,
            'doc_id'     => 'PLANNING.md',
            'provider'   => 'mock',
            'status'     => 'done',
        ]);

        (new GenerateDocumentJob($project->id, 'PLANNING.md', $aiJob->id))
            ->handle($this->app->make(\App\Services\Ai\DocumentGenerator::class));

        $this->assertSame(0, $counting->calls);
    }

    public function test_job_with_missing_project_is_a_noop(): void
    {
        $job = new GenerateDocumentJob('00000000-0000-0000-0000-000000000000', 'PLANNING.md', 'x');

        $job->handle($this->app->make(\App\Services\Ai\DocumentGenerator::class));

        $this->assertSame(0, AiJob::count());
    }

    /* -------------------------------------------------------------- */

    private function seedMinimalProject(): void
    {
        $this->postJson('/api/wizard/intake', [
            'project_name' => 'Queue Mode',
            'project_goal' => 'Menguji pipeline generate berbasis queue.',
            'target_users' => 'Engineer internal.',
            'known_constraints' => null,
        ])->assertOk();

        $this->postJson('/api/wizard/domain', [
            'domain_category'     => 'Internal Tool',
            'problem_statement'   => 'Generate sinkron melewati batas timeout proxy.',
            'value_proposition'   => 'Queue membuat proses tahan lama aman.',
            'scale_estimate_mvp'  => '<100',
            'scale_estimate_12mo' => '100-1k',
        ])->assertOk();

        $this->postJson('/api/wizard/scope', [
            'p0_features'  => ['Dispatch job', 'Polling status'],
            'p1_features'  => [],
            'p2_features'  => [],
            'out_of_scope' => [],
        ])->assertOk();

        $this->postJson('/api/wizard/architecture', [
            'preferred_stack'    => 'Laravel+Blade',
            'hosting_preference' => 'WSL',
            'known_integrations' => [],
            'data_sensitivity'   => 'Internal',
        ])->assertOk();
    }
}
