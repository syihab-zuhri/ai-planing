<?php

namespace App\Http\Controllers;

use App\Models\AiJob;
use App\Models\Project;
use App\Services\Ai\AiProviderInterface;
use App\Services\WizardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $allSucceeded = count($results) === count(self::DOCUMENT_IDS)
            && collect($results)->every(fn (array $result) => $result['status'] === 'done');

        if ($allSucceeded) {
            $project->setGate('B');
            $project->save();
        }

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
        if (!in_array($docId, self::DOCUMENT_IDS, true)) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'Document ID tidak valid.',
                ],
            ], 422);
        }

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
     * SSE endpoint — streams progress events for each completed document,
     * a final 'complete' event when the batch finishes, and 'error' events
     * for failures.
     *
     * Event types:
     *   - progress: emitted per completed/failed doc  {doc_id, status, current, total}
     *   - complete: emitted once when all docs processed {project_id, total, done, failed}
     *   - error:    emitted on fatal/project-level errors {code, message}
     *
     * Timeout: 120 seconds max.
     */
    public function stream(Request $request): StreamedResponse|JsonResponse
    {
        $sessionId = $request->session()->getId();
        $project = $this->wizard->getState($sessionId);

        if (!$project) {
            return response()->json([
                'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Project not found'],
            ], 404);
        }

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

        $controller = $this;

        return new StreamedResponse(function () use ($project, $controller) {
            set_time_limit(120);

            $total = count(self::DOCUMENT_IDS);
            $current = 0;
            $doneCount = 0;
            $failedCount = 0;

            foreach (self::DOCUMENT_IDS as $docId) {
                $current++;

                try {
                    $result = $controller->streamRunOne($project, $docId);

                    if ($result['status'] === 'done') {
                        $doneCount++;

                        $controller->sendSseEvent('progress', [
                            'doc_id'  => $docId,
                            'status'  => 'done',
                            'current' => $current,
                            'total'   => $total,
                        ]);
                    } else {
                        $failedCount++;

                        $controller->sendSseEvent('error', [
                            'code'    => 'GENERATION_FAILED',
                            'message' => $result['error'] ?? 'Unknown error',
                            'doc_id'  => $docId,
                            'current' => $current,
                            'total'   => $total,
                        ]);
                    }
                } catch (\Throwable $e) {
                    $failedCount++;

                    $controller->sendSseEvent('error', [
                        'code'    => 'GENERATION_FAILED',
                        'message' => substr($e->getMessage(), 0, 200),
                        'doc_id'  => $docId,
                        'current' => $current,
                        'total'   => $total,
                    ]);
                }
            }

            // Set Gate B if all succeeded.
            if ($doneCount === $total) {
                $project->setGate('B');
                $project->save();
            }

            $controller->sendSseEvent('complete', [
                'project_id' => $project->id,
                'total'      => $total,
                'done'       => $doneCount,
                'failed'     => $failedCount,
            ]);
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Send a single SSE event to the output stream.
     *
     * Format: "event: {type}\ndata: {json}\n\n"
     */
    public function sendSseEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";

        // Flush for real HTTP connections (safe no-op if no OB active).
        if (!app()->runningUnitTests()) {
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }
    }

    /**
     * Run a single document generation — public wrapper around runOne()
     * so the streaming closure can access it.
     */
    public function streamRunOne(Project $project, string $docId): array
    {
        return $this->runOne($project, $docId);
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