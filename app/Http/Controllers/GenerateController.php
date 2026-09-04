<?php

namespace App\Http\Controllers;

use App\Models\AiJob;
use App\Models\Project;
use App\Services\Ai\AiProviderInterface;
use App\Services\WizardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * GenerateController — endpoint pipeline generation dokumen.
 *
 * Acuan: PRD/GENERATION.md + API.md §3.
 *
 * Phase 1 backend: dispatch AI jobs ke queue (sync di Phase 1 supaya test
 * mudah). Job akan:
 *   - Memanggil AiProviderInterface untuk tiap dokumen (mock di Phase 1).
 *   - Mencatat audit ke ai_jobs (provider, token_in/out, latency, status).
 *   - Memperbarui project.current_gate saat progress.
 */
class GenerateController extends Controller
{
    /**
     * Daftar 18+ dokumen sesuai PROJECT_MANIFEST (lihat PRD/EXPORT.md §7).
     *
     * @var string[]
     */
    public const DOCUMENT_IDS = [
        'PROJECT_MANIFEST.md',
        'PLANNING.md',
        'SRS.md',
        'PRD/_INDEX.md',
        'PRD/INTAKE.md',
        'PRD/CLARIFICATION.md',
        'PRD/GENERATION.md',
        'PRD/VALIDATION.md',
        'PRD/EXPORT.md',
        'ERD.md',
        'API.md',
        'ARCHITECTURE.md',
        'SECURITY.md',
        'DSD.md',
        'TESTING.md',
        'TASKS.md',
        'ENVIRONMENT.md',
        'RUNBOOK.md',
        'ADR/ADR-001-stack.md',
        'ADR/ADR-002-no-auth-mvp.md',
        'TRACEABILITY.md',
        'CHANGELOG.md',
        'agent.md',
    ];

    public function __construct(
        private readonly WizardService $wizard,
        private readonly AiProviderInterface $aiProvider,
    ) {
    }

    /**
     * API-GENERATE-START (POST /api/generate/start).
     * Dispatch semua 18+ job. Phase 1: jalan synchronous untuk sederhana.
     */
    public function start(Request $request): JsonResponse
    {
        $sessionId = $request->session()->getId();
        $project = $this->wizard->getOrCreate($sessionId);

        // Guard: state wizard minimal harus sudah ada intake.
        $state = $project->draft_state ?? [];
        if (empty($state['intake']['project_name'])) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_FAILED',
                    'message' => 'Wizard belum lengkap. Selesaikan Step 1-4 terlebih dahulu.',
                ],
            ], 422);
        }

        $batchId = (string) Str::uuid();
        $results = [];

        foreach (self::DOCUMENT_IDS as $docId) {
            $results[] = $this->runOne($project, $docId);
        }

        // Naikkan gate ke B setelah generate selesai (minimal).
        $project->setGate('B');
        $project->save();

        return response()->json([
            'batch_id'  => $batchId,
            'project_id'=> $project->id,
            'total'     => count($results),
            'results'   => $results,
        ]);
    }

    /**
     * API-GENERATE-RETRY (POST /api/generate/retry/{doc_id}).
     */
    public function retry(Request $request, string $docId): JsonResponse
    {
        $sessionId = $request->session()->getId();
        $project = $this->wizard->getState($sessionId);
        if (!$project) {
            return response()->json(['error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Project not found']], 404);
        }

        $result = $this->runOne($project, $docId);
        return response()->json($result);
    }

    /**
     * API-GENERATE-CANCEL (POST /api/generate/cancel).
     *
     * Phase 1: tidak ada job asynchronous — endpoint ini membatalkan
     * antrean (no-op di Phase 1). Implementasi lengkap menyusul setelah
     * queue worker di-deploy.
     */
    public function cancel(Request $request): JsonResponse
    {
        $sessionId = $request->session()->getId();
        $project = $this->wizard->getState($sessionId);
        if ($project) {
            // Tandai semua queued ai_jobs untuk project ini sebagai cancelled.
            AiJob::where('project_id', $project->id)
                ->whereIn('status', ['queued', 'running'])
                ->update(['status' => 'cancelled', 'completed_at' => now()]);
        }

        return response()->json(['cancelled' => true]);
    }

    /**
     * API-GENERATE-STREAM (GET /api/generate/stream).
     *
     * Phase 1: kembalikan snapshot status (SSE scaffolding menyusul).
     */
    public function stream(Request $request)
    {
        $sessionId = $request->session()->getId();
        $project = $this->wizard->getState($sessionId);

        if (!$project) {
            return response()->json(['error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Project not found']], 404);
        }

        $jobs = AiJob::where('project_id', $project->id)
            ->orderBy('created_at')
            ->get(['doc_id', 'status', 'token_in', 'token_out', 'latency_ms']);

        return response()->json([
            'project_id' => $project->id,
            'jobs'       => $jobs,
        ]);
    }

    /* -------------------------------------------------------------- */
    /*  Internal                                                     */
    /* -------------------------------------------------------------- */

    /**
     * Jalankan satu dokumen. Memanggil AiProviderInterface (mock di Phase 1).
     */
    private function runOne(Project $project, string $docId): array
    {
        $job = AiJob::create([
            'project_id' => $project->id,
            'doc_id'     => $docId,
            'provider'   => $this->aiProvider->name(),
            'status'     => 'running',
        ]);

        try {
            $messages = $this->buildPromptMessages($project, $docId);
            $response = $this->aiProvider->chat($messages);

            // Simpan ke draft_state.documents.
            $state = $project->draft_state ?? [];
            $documents = (array) ($state['documents'] ?? []);
            $documents[$docId] = $response->content;
            $state['documents'] = $documents;
            $project->draft_state = $state;
            $project->save();

            $job->update([
                'status'       => 'done',
                'token_in'     => $response->tokens_in,
                'token_out'    => $response->tokens_out,
                'latency_ms'   => $response->latency_ms,
                'completed_at' => now(),
            ]);

            return [
                'doc_id'   => $docId,
                'status'   => 'done',
                'tokens_in' => $response->tokens_in,
                'tokens_out'=> $response->tokens_out,
                'latency_ms'=> $response->latency_ms,
            ];
        } catch (\Throwable $e) {
            $job->update([
                'status'        => 'failed',
                'error_message' => substr($e->getMessage(), 0, 500),
                'completed_at'  => now(),
            ]);

            return [
                'doc_id'  => $docId,
                'status'  => 'failed',
                'error'   => substr($e->getMessage(), 0, 200),
            ];
        }
    }

    /**
     * Susun pesan prompt untuk dokumen tertentu. Placeholder — produksi akan
     * memakai PLANNING_v3 sebagai system role (lihat PRD/GENERATION §7a).
     */
    private function buildPromptMessages(Project $project, string $docId): array
    {
        $state = $project->draft_state ?? [];
        $context = json_encode([
            'project_name' => $state['intake']['project_name'] ?? '',
            'doc_id'       => $docId,
        ], JSON_UNESCAPED_UNICODE);

        return [
            ['role' => 'system', 'content' => "Generate the {$docId} document following PLANNING_v3 conventions."],
            ['role' => 'user',   'content' => "Project context: {$context}"],
        ];
    }
}