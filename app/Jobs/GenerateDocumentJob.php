<?php

namespace App\Jobs;

use App\Models\AiJob;
use App\Models\Project;
use App\Services\Ai\DocumentGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * GenerateDocumentJob — satu job per dokumen (PRD/GENERATION.md §7, TASK-P3-004).
 *
 * Dijalankan oleh worker `blueprintforge-worker.service` pada queue
 * `blueprintforge`. Job disengaja tipis: seluruh logika ada di DocumentGenerator
 * supaya jalur retry manual (controller) dan jalur queue berperilaku identik.
 *
 * Catatan: job menyimpan ID (string), bukan model, agar payload queue kecil dan
 * tidak menyimpan draft_state pengguna di tabel jobs.
 */
class GenerateDocumentJob implements ShouldQueue
{
    use Queueable;

    /**
     * BR-GEN-002/NFR-015: percobaan queue-level di atas retry provider-level.
     */
    public int $tries = 3;

    /**
     * Timeout job harus lebih besar dari timeout HTTP provider.
     */
    public int $timeout = 300;

    public function __construct(
        public readonly string $projectId,
        public readonly string $docId,
        public readonly string $aiJobId,
    ) {
        $this->onQueue('blueprintforge');
    }

    /**
     * Cegah dua job untuk dokumen yang sama berjalan bersamaan.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->projectId . ':' . $this->docId))
                ->releaseAfter(10)
                ->expireAfter(600),
        ];
    }

    public function handle(DocumentGenerator $generator): void
    {
        $project = Project::find($this->projectId);

        if ($project === null) {
            return;
        }

        $job = AiJob::find($this->aiJobId);

        // Sudah selesai (mis. retry manual mendahului) → jangan generate ulang.
        if ($job !== null && $job->status === 'done') {
            return;
        }

        $generator->generate($project, $this->docId, $job);

        $this->promoteGateIfComplete($project);
    }

    /**
     * Naikkan Gate B hanya bila SELURUH dokumen wajib sudah tersimpan
     * (sesuai pengetatan gate di commit 344d3b6).
     */
    private function promoteGateIfComplete(Project $project): void
    {
        $project->refresh();

        $documents = (array) (($project->draft_state ?? [])['documents'] ?? []);
        $required = \App\Http\Controllers\GenerateController::DOCUMENT_IDS;

        foreach ($required as $docId) {
            if (!isset($documents[$docId]) || trim((string) $documents[$docId]) === '') {
                return;
            }
        }

        if ($project->current_gate === 'A') {
            $project->setGate('B');
            $project->save();
        }
    }

    /**
     * Kegagalan permanen setelah seluruh tries habis — tandai ai_job supaya UI
     * bisa menawarkan retry manual.
     */
    public function failed(\Throwable $e): void
    {
        $job = AiJob::find($this->aiJobId);

        if ($job !== null && $job->status !== 'done') {
            $job->update([
                'status'        => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'completed_at'  => now(),
            ]);
        }
    }
}
