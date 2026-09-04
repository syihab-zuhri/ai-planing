<?php

namespace Tests\Feature;

use App\Models\Export;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportApiTest extends TestCase
{

    /**
     * Helper: seed a project with full wizard state + documents at Gate B.
     */
    private function seedProjectWithDocuments(string $sessionId): Project
    {
        $project = Project::create([
            'id'               => fake()->uuid(),
            'session_id'       => $sessionId,
            'current_gate'     => 'B',
            'draft_state'      => $this->fullState(),
            'last_activity_at' => now(),
        ]);

        return $project;
    }

    private function fullState(): array
    {
        $docs = [];
        foreach (\App\Http\Controllers\GenerateController::DOCUMENT_IDS as $docId) {
            $docs[$docId] = "# {$docId}\n\n" . str_repeat('Lorem ipsum dolor sit amet. ', 20);
        }

        return [
            'intake' => [
                'project_name'      => 'Test Project',
                'project_goal'      => 'Build something cool and useful for the world',
                'target_users'      => 'Developers',
                'known_constraints' => null,
            ],
            'domain' => [
                'domain_category'     => 'SaaS',
                'problem_statement'   => 'Need a better tool',
                'value_proposition'   => 'Fast and reliable',
                'scale_estimate_mvp'  => '100',
                'scale_estimate_12mo' => '10000',
            ],
            'scope' => [
                'p0_features'  => ['CRUD', 'Auth'],
                'p1_features'  => ['Reports'],
                'p2_features'  => [],
                'out_of_scope' => ['Mobile'],
            ],
            'architecture' => [
                'preferred_stack'    => 'Laravel',
                'hosting_preference' => 'AWS',
                'known_integrations' => [],
                'data_sensitivity'   => 'Public',
            ],
            'documents'      => $docs,
            'clarifications' => [],
            'validation'     => null,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  POST /api/export/start                                            */
    /* ------------------------------------------------------------------ */

    public function test_start_without_project_returns_404(): void
    {
        $response = $this->withSession([])->postJson('/api/export/start');
        $response->assertStatus(404)
                 ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_start_with_gate_a_returns_403(): void
    {
        $response = $this->withSession([])->postJson('/api/wizard/start');
        // Project created at Gate A
        $response = $this->postJson('/api/export/start');
        $response->assertStatus(403)
                 ->assertJsonPath('error.code', 'GATE_INSUFFICIENT');
    }

    public function test_start_with_no_documents_returns_422(): void
    {
        $sessionId = session()->getId();
        $project = Project::create([
            'id'               => fake()->uuid(),
            'session_id'       => $sessionId,
            'current_gate'     => 'B',
            'draft_state'      => [
                'intake'    => ['project_name' => 'X', 'project_goal' => 'Y'],
                'documents' => [],
            ],
            'last_activity_at' => now(),
        ]);

        $response = $this->withSession(['_token' => 'x'])
                         ->postJson('/api/export/start');

        $response->assertStatus(422)
                 ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_start_success_creates_export_and_zip(): void
    {
        Storage::fake('local');

        $sessionId = session()->getId();
        $project = $this->seedProjectWithDocuments($sessionId);

        $response = $this->withSession(['_token' => 'x'])
                         ->postJson('/api/export/start');

        $response->assertOk()
                 ->assertJsonStructure(['export_id', 'download_url', 'expires_at']);

        $this->assertDatabaseHas('exports', [
            'project_id' => $project->id,
        ]);

        $export = Export::where('project_id', $project->id)->first();
        $this->assertNotNull($export);
        $this->assertNotNull($export->download_token);
    }

    public function test_start_with_overrides_includes_override_md(): void
    {
        Storage::fake('local');

        $sessionId = session()->getId();
        $state = $this->fullState();
        $state['overrides'] = [
            [
                'id'         => 'override-1',
                'gate'       => 'C',
                'reason'     => 'Approved by PM after review meeting.',
                'created_at' => now()->toIso8601String(),
            ],
        ];

        $project = Project::create([
            'id'               => fake()->uuid(),
            'session_id'       => $sessionId,
            'current_gate'     => 'B',
            'draft_state'      => $state,
            'last_activity_at' => now(),
        ]);

        $response = $this->withSession(['_token' => 'x'])
                         ->postJson('/api/export/start');

        $response->assertOk();
    }

    /* ------------------------------------------------------------------ */
    /*  GET /api/export/download/{token}                                  */
    /* ------------------------------------------------------------------ */

    public function test_download_invalid_token_returns_404(): void
    {
        $response = $this->getJson('/api/export/download/nonexistent-token');
        $response->assertStatus(404)
                 ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_download_expired_token_returns_403(): void
    {
        $project = Project::create([
            'id'               => fake()->uuid(),
            'session_id'       => 'sess-expired',
            'current_gate'     => 'B',
            'draft_state'      => [],
            'last_activity_at' => now(),
        ]);

        $export = Export::create([
            'project_id'     => $project->id,
            'file_path'      => 'exports/test.zip',
            'file_size'      => 100,
            'download_token' => 'expired-token-123',
            'expires_at'     => now()->subHour(),
        ]);

        $response = $this->getJson('/api/export/download/expired-token-123');
        $response->assertStatus(403);
    }

    public function test_download_missing_file_returns_500(): void
    {
        $project = Project::create([
            'id'               => fake()->uuid(),
            'session_id'       => 'sess-missingfile',
            'current_gate'     => 'B',
            'draft_state'      => [],
            'last_activity_at' => now(),
        ]);

        $export = Export::create([
            'project_id'     => $project->id,
            'file_path'      => 'exports/nonexistent.zip',
            'file_size'      => 100,
            'download_token' => 'valid-token-missing-file',
            'expires_at'     => now()->addHour(),
        ]);

        $response = $this->getJson('/api/export/download/valid-token-missing-file');
        $response->assertStatus(500)
                 ->assertJsonPath('error.code', 'INTERNAL');
    }

    public function test_download_valid_returns_zip(): void
    {
        // Create a real zip file on disk
        $zipPath = storage_path('app/private/exports/download-test.zip');
        @mkdir(dirname($zipPath), 0755, true);
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('test.md', '# Test');
        $zip->close();

        $project = Project::create([
            'id'               => fake()->uuid(),
            'session_id'       => 'sess-download-ok',
            'current_gate'     => 'B',
            'draft_state'      => [],
            'last_activity_at' => now(),
        ]);

        $export = Export::create([
            'project_id'     => $project->id,
            'file_path'      => 'exports/download-test.zip',
            'file_size'      => filesize($zipPath),
            'download_token' => 'good-download-token',
            'expires_at'     => now()->addHour(),
        ]);

        $response = $this->get('/api/export/download/good-download-token');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/zip');

        // Cleanup
        @unlink($zipPath);
    }
}
