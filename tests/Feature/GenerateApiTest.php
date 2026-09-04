<?php

namespace Tests\Feature;

use App\Models\AiJob;
use App\Models\Project;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\MockAiProvider;
use Tests\TestCase;

/**
 * GenerateApiTest — pipeline generate dengan AiProviderInterface di-mock.
 *
 * Acuan: PRD/GENERATION.md + API.md §3.
 * Phase 1 backend TIDAK memanggil 9router (lihat instruksi agent: stub/mock).
 */
class GenerateApiTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        // Bind provider mock yang deterministic untuk test.
        $mock = new MockAiProvider();
        $mock->mockContent = "# Test Doc\n\nContent for " . str_repeat('x', 250);
        $this->app->instance(AiProviderInterface::class, $mock);
    }

    /**
     * POST /api/generate/start dengan intake kosong → 422 VALIDATION_FAILED.
     */
    public function test_start_without_intake_returns_422(): void
    {
        $this->postJson('/api/generate/start')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * POST /api/generate/start setelah wizard lengkap → 200 dengan list job.
     */
    public function test_start_runs_all_documents_through_mock_provider(): void
    {
        // Setup state minimum via wizard endpoint.
        $this->postJson('/api/wizard/intake', [
            'project_name'      => 'Alpha',
            'project_goal'      => 'A small web tool for internal team alignment.',
            'target_users'      => 'Engineering team of 5 people.',
            'known_constraints' => null,
        ])->assertOk();

        $this->postJson('/api/wizard/domain', [
            'domain_category'     => 'Web',
            'problem_statement'   => 'Team alignment takes too long without docs.',
            'value_proposition'   => 'Quick structured plans for every project.',
            'scale_estimate_mvp'  => '<100',
            'scale_estimate_12mo' => '100-1k',
        ])->assertOk();

        $this->postJson('/api/wizard/scope', [
            'p0_features'  => ['Intake', 'Generate'],
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

        $response = $this->postJson('/api/generate/start')->assertOk();

        $project = Project::first();
        $this->assertNotNull($project);

        // Semua dokumen harus ada di ai_jobs.
        $this->assertGreaterThanOrEqual(18, AiJob::where('project_id', $project->id)->count());

        // Setiap ai_job harus status 'done' (mock tidak gagal).
        $this->assertSame(0, AiJob::where('project_id', $project->id)
            ->where('status', '!=', 'done')->count());

        // Provider harus 'mock' (sesuai setUp).
        $this->assertSame('mock', AiJob::where('project_id', $project->id)->value('provider'));

        // Gate naik ke B setelah generate sukses.
        $project->refresh();
        $this->assertContains($project->current_gate, ['B', 'C', 'D']);

        // Documents tersimpan ke draft_state.documents.
        $this->assertNotEmpty($project->draft_state['documents']);
        $this->assertArrayHasKey('PLANNING.md', $project->draft_state['documents']);
    }

    /**
     * POST /api/generate/retry/{doc_id} untuk satu dokumen.
     */
    public function test_retry_runs_only_one_document(): void
    {
        $this->seedMinimalProject();

        $project = Project::first();
        $before = AiJob::where('project_id', $project->id)->count();

        $response = $this->postJson('/api/generate/retry/PLANNING.md')
            ->assertOk();

        $response->assertJsonPath('doc_id', 'PLANNING.md');
        $response->assertJsonPath('status', 'done');

        // Retry menambah 1 record ai_job.
        $after = AiJob::where('project_id', $project->id)->count();
        $this->assertSame($before + 1, $after);
    }

    public function test_retry_rejects_unknown_document_id(): void
    {
        $this->seedMinimalProject();

        $this->postJson('/api/generate/retry/../evil.md')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->postJson('/api/generate/retry/UNKNOWN.md')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * POST /api/generate/cancel → no-op jika tidak ada job running.
     */
    public function test_cancel_returns_success(): void
    {
        $this->seedMinimalProject();

        $this->postJson('/api/generate/cancel')
            ->assertOk()
            ->assertJsonPath('cancelled', true);
    }

    /**
     * GET /api/generate/stream → snapshot status job.
     */
    public function test_stream_returns_jobs_snapshot(): void
    {
        $this->seedMinimalProject();
        $this->postJson('/api/generate/start')->assertOk();

        $response = $this->getJson('/api/generate/stream')->assertOk();

        $response->assertJsonStructure(['project_id', 'jobs']);
        $this->assertNotEmpty($response->json('jobs'));
    }

    /* -------------------------------------------------------------- */

    private function seedMinimalProject(): void
    {
        $this->postJson('/api/wizard/intake', [
            'project_name' => 'Beta',
            'project_goal' => 'A simple internal API for our team to coordinate work.',
            'target_users' => 'Engineers and PM in the same team.',
            'known_constraints' => null,
        ])->assertOk();

        $this->postJson('/api/wizard/domain', [
            'domain_category'     => 'API',
            'problem_statement'   => 'Coordination overhead across tools is too high.',
            'value_proposition'   => 'One source of truth for project signals.',
            'scale_estimate_mvp'  => '<100',
            'scale_estimate_12mo' => '100-1k',
        ])->assertOk();

        $this->postJson('/api/wizard/scope', [
            'p0_features'  => ['Auth', 'CRUD'],
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