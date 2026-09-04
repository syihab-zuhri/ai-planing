<?php

namespace Tests\Feature;

use App\Models\AiJob;
use App\Models\Export;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupCommandsTest extends TestCase
{
    // ─── CleanupExpiredExports ───────────────────────────────────────

    public function test_cleanup_exports_removes_expired_rows_and_files(): void
    {
        Storage::fake('local');

        $project = Project::create([
            'session_id'       => 'sess-cleanup-exp-1',
            'draft_state'      => [],
            'current_gate'     => 'A',
            'last_activity_at' => now(),
        ]);

        // Expired > 24h — should be deleted
        $expiredExport = Export::create([
            'project_id'     => $project->id,
            'file_path'      => "exports/{$project->id}.zip",
            'file_size'      => 1024,
            'download_token' => 'token-expired-1',
            'expires_at'     => now()->subHours(25),
        ]);

        Storage::disk('local')->put("exports/{$project->id}.zip", 'fake-zip-content');

        // Not yet expired — should NOT be deleted
        $freshExport = Export::create([
            'project_id'     => $project->id,
            'file_path'      => 'exports/fresh.zip',
            'file_size'      => 512,
            'download_token' => 'token-fresh-1',
            'expires_at'     => now()->addHours(1),
        ]);

        Storage::disk('local')->put('exports/fresh.zip', 'fresh-content');

        $this->artisan('cleanup:exports')
            ->expectsOutputToContain('1 expired export')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('exports', ['id' => $expiredExport->id]);
        $this->assertDatabaseHas('exports', ['id' => $freshExport->id]);

        Storage::disk('local')->assertMissing("exports/{$project->id}.zip");
        Storage::disk('local')->assertExists('exports/fresh.zip');
    }

    public function test_cleanup_exports_skips_recently_expired(): void
    {
        $project = Project::create([
            'session_id'       => 'sess-cleanup-exp-2',
            'draft_state'      => [],
            'current_gate'     => 'A',
            'last_activity_at' => now(),
        ]);

        // Expired only 2h ago (< 24h cutoff) — should NOT be deleted
        Export::create([
            'project_id'     => $project->id,
            'file_path'      => 'exports/recent.zip',
            'file_size'      => 256,
            'download_token' => 'token-recent-1',
            'expires_at'     => now()->subHours(2),
        ]);

        $this->artisan('cleanup:exports')
            ->expectsOutputToContain('No expired exports')
            ->assertExitCode(0);

        $this->assertDatabaseCount('exports', 1);
    }

    public function test_cleanup_exports_handles_missing_file_gracefully(): void
    {
        Storage::fake('local');

        $project = Project::create([
            'session_id'       => 'sess-cleanup-exp-3',
            'draft_state'      => [],
            'current_gate'     => 'A',
            'last_activity_at' => now(),
        ]);

        // Expired row but ZIP file doesn't exist on disk
        $export = Export::create([
            'project_id'     => $project->id,
            'file_path'      => 'exports/nonexistent.zip',
            'file_size'      => 0,
            'download_token' => 'token-nofile-1',
            'expires_at'     => now()->subHours(48),
        ]);

        $this->artisan('cleanup:exports')
            ->expectsOutputToContain('1 expired export')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('exports', ['id' => $export->id]);
    }

    // ─── CleanupInactiveProjects ────────────────────────────────────

    public function test_cleanup_projects_removes_inactive_with_relations(): void
    {
        Storage::fake('local');

        // Inactive project (> 7 days)
        $inactive = Project::create([
            'session_id'       => 'sess-cleanup-proj-1',
            'draft_state'      => [],
            'current_gate'     => 'B',
            'last_activity_at' => now()->subDays(8),
        ]);

        AiJob::create([
            'project_id' => $inactive->id,
            'doc_id'     => 'doc-1',
            'provider'   => 'openai',
            'status'     => 'completed',
        ]);

        Export::create([
            'project_id'     => $inactive->id,
            'file_path'      => "exports/{$inactive->id}.zip",
            'file_size'      => 2048,
            'download_token' => 'token-inactive-1',
            'expires_at'     => now()->subDays(1),
        ]);

        Storage::disk('local')->put("exports/{$inactive->id}.zip", 'zip-content');

        // Active project (< 7 days) — should survive
        $active = Project::create([
            'session_id'       => 'sess-cleanup-proj-2',
            'draft_state'      => [],
            'current_gate'     => 'A',
            'last_activity_at' => now()->subDays(1),
        ]);

        AiJob::create([
            'project_id' => $active->id,
            'doc_id'     => 'doc-2',
            'provider'   => 'openai',
            'status'     => 'queued',
        ]);

        $this->artisan('cleanup:projects')
            ->expectsOutputToContain('1 inactive project')
            ->assertExitCode(0);

        // Inactive project and relations gone
        $this->assertDatabaseMissing('projects', ['id' => $inactive->id]);
        $this->assertDatabaseMissing('ai_jobs', ['project_id' => $inactive->id]);
        $this->assertDatabaseMissing('exports', ['project_id' => $inactive->id]);
        Storage::disk('local')->assertMissing("exports/{$inactive->id}.zip");

        // Active project and relations remain
        $this->assertDatabaseHas('projects', ['id' => $active->id]);
        $this->assertDatabaseHas('ai_jobs', ['project_id' => $active->id]);
    }

    public function test_cleanup_projects_skips_active(): void
    {
        $active = Project::create([
            'session_id'       => 'sess-cleanup-proj-3',
            'draft_state'      => [],
            'current_gate'     => 'A',
            'last_activity_at' => now()->subDays(3),
        ]);

        $this->artisan('cleanup:projects')
            ->expectsOutputToContain('No inactive projects')
            ->assertExitCode(0);

        $this->assertDatabaseHas('projects', ['id' => $active->id]);
    }

    public function test_cleanup_projects_handles_project_without_relations(): void
    {
        // Inactive project with no ai_jobs or exports
        $lonely = Project::create([
            'session_id'       => 'sess-cleanup-proj-4',
            'draft_state'      => [],
            'current_gate'     => 'A',
            'last_activity_at' => now()->subDays(10),
        ]);

        $this->artisan('cleanup:projects')
            ->expectsOutputToContain('1 inactive project')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('projects', ['id' => $lonely->id]);
    }
}
