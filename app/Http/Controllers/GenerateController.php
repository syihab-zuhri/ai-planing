<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDocumentJob;
use App\Models\AiJob;
use App\Models\Project;
use App\Services\Ai\AiProviderInterface;
use App\Services\Ai\DocumentGenerator;
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
 * Dua mode eksekusi (config `ai.generation.mode`):
 *   - queue (default produksi): setiap dokumen di-dispatch sebagai
 *     GenerateDocumentJob ke queue `blueprintforge`. Endpoint kembali 202
 *     seketika; progres dipantau via /api/generate/status atau SSE.
 *     Wajib untuk provider sungguhan — 23 dokumen × ~40 detik jauh melewati
 *     batas proxy_read_timeout Nginx.
 *   - sync: dokumen digenerate inline dalam request. Dipakai test & debugging.
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
        private readonly DocumentGenerator $generator,
    ) {
    }

    /**
     * API-GENERATE-START (POST /api/generate/start).
     */
    public function start(Request $request): JsonResponse
    {
        $sessionId = $request->session()->getId();
        $project = $this->wizard->getOrCreate($sessionId);

        if (($guard = $this->guardWizardComplete($project)) !== null) {
            return $guard;
        }

        $batchId = (string) Str::uuid();

        return $this->queueMode()
            ? $this->startQueued($project, $batchId)
            : $this->startSync($project, $batchId);
    }

    /**
     * API-GENERATE-STATUS (GET /api/generate/status).
     *
     * Polling ringan untuk UI: rekap status per dokumen dari ai_jobs +
     * draft_state.documents. Dipakai juga sebagai fallback bila SSE gagal
     * (PRD/GENERATION.md §10).
     */
    public function status(Request $request): JsonResponse
    {
        $sessionId = $request->session()->getId();
        $project = $this->wizard->getState($sessionId);

        if ($project === null) {
            return response()->json([
                'error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Project not found'],
            ], 404);
        }

        return response()->json($this->progressSnapshot($project));
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

        if ($this->queueMode()) {
            $job = $this->createQueuedJob($project, $docId);
            GenerateDocumentJob::dispatch($project->id, $docId, $job->id);

            return response()->json([
                'doc_id' => $docId,
                'status' => 'queued',
                'job_id' => $job->id,
            ], 202);
        }

        return response()->json($this->generator->generate($project, $docId));
    }

    /**
     * API-GENERATE-CANCEL (POST /api/generate/cancel).
     *
     * Menandai job queued/running sebagai cancelled. GenerateDocumentJob
     * memeriksa status ai_job sebelum memanggil provider, sehingga job yang
     * belum diambil worker tidak akan menghasilkan panggilan AI.
     */
    public function cancel(Request $request): JsonResponse
    {
        $sessionId = $request->session()->getId();
        $project = $this->wizard->getState($sessionId);
        if ($project) {
            AiJob::where('project_id', $project->id)
                ->whereIn('status', ['queued', 'running'])
                ->update(['status' => 'cancelled', 'completed_at' => now()]);
        }

        return response()->json(['cancelled' => true]);
    }

    /**
     * API-GENERATE-STREAM (GET /api/generate/stream).
     *
     * Event: progress (per dokumen), complete (akhir batch), error (kegagalan).
     *
     * Mode queue  → stream MEMANTAU progres worker (tidak memanggil AI sendiri).
     * Mode sync   → stream menjalankan generate inline, satu event per dokumen.
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

        if (($guard = $this->guardWizardComplete($project)) !== null) {
            return $guard;
        }

        return $this->queueMode()
            ? $this->streamWatch($project)
            : $this->streamInline($project);
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
     * Run a single document generation — public wrapper so the streaming
     * closure can reach it.
     */
    public function streamRunOne(Project $project, string $docId): array
    {
        return $this->generator->generate($project, $docId);
    }

    /* -------------------------------------------------------------- */
    /*  Mode: queue                                                   */
    /* -------------------------------------------------------------- */

    private function startQueued(Project $project, string $batchId): JsonResponse
    {
        $results = [];

        foreach (self::DOCUMENT_IDS as $docId) {
            $job = $this->createQueuedJob($project, $docId);
            GenerateDocumentJob::dispatch($project->id, $docId, $job->id);

            $results[] = [
                'doc_id' => $docId,
                'status' => 'queued',
                'job_id' => $job->id,
            ];
        }

        return response()->json([
            'batch_id'   => $batchId,
            'project_id' => $project->id,
            'mode'       => 'queue',
            'total'      => count($results),
            'provider'   => $this->aiProvider->name(),
            'results'    => $results,
        ], 202);
    }

    private function createQueuedJob(Project $project, string $docId): AiJob
    {
        return AiJob::create([
            'project_id' => $project->id,
            'doc_id'     => $docId,
            'provider'   => $this->aiProvider->name(),
            'status'     => 'queued',
        ]);
    }

    /**
     * SSE watcher: polling DB setiap detik dan mengirim event begitu ada
     * dokumen baru yang selesai/gagal. Berhenti saat semua dokumen terhitung
     * atau saat batas waktu tercapai (klien boleh reconnect).
     */
    private function streamWatch(Project $project): StreamedResponse
    {
        $controller = $this;

        return new StreamedResponse(function () use ($project, $controller) {
            $maxSeconds = (int) config('ai.generation.stream_timeout_seconds', 900);
            @set_time_limit($maxSeconds + 30);
            ignore_user_abort(false);

            $total = count(self::DOCUMENT_IDS);
            $seen = [];
            $deadline = microtime(true) + $maxSeconds;

            while (microtime(true) < $deadline) {
                $jobs = AiJob::where('project_id', $project->id)
                    ->whereIn('doc_id', self::DOCUMENT_IDS)
                    ->whereIn('status', ['done', 'failed', 'cancelled'])
                    ->orderBy('completed_at')
                    ->get(['doc_id', 'status', 'error_message']);

                foreach ($jobs as $job) {
                    if (isset($seen[$job->doc_id])) {
                        continue;
                    }

                    $seen[$job->doc_id] = $job->status;
                    $current = count($seen);

                    if ($job->status === 'done') {
                        $controller->sendSseEvent('progress', [
                            'doc_id'  => $job->doc_id,
                            'status'  => 'done',
                            'current' => $current,
                            'total'   => $total,
                        ]);
                    } else {
                        $controller->sendSseEvent('error', [
                            'code'    => 'GENERATION_FAILED',
                            'message' => (string) ($job->error_message ?? 'Unknown error'),
                            'doc_id'  => $job->doc_id,
                            'current' => $current,
                            'total'   => $total,
                        ]);
                    }
                }

                if (count($seen) >= $total) {
                    break;
                }

                if (connection_aborted()) {
                    return;
                }

                // Heartbeat comment agar proxy tidak menutup koneksi idle.
                echo ": keepalive\n\n";
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();

                sleep(1);
            }

            $doneCount = count(array_filter($seen, fn ($status) => $status === 'done'));

            $controller->sendSseEvent('complete', [
                'project_id' => $project->id,
                'total'      => $total,
                'done'       => $doneCount,
                'failed'     => count($seen) - $doneCount,
                'timeout'    => count($seen) < $total,
            ]);
        }, 200, $this->sseHeaders());
    }

    /* -------------------------------------------------------------- */
    /*  Mode: sync                                                    */
    /* -------------------------------------------------------------- */

    private function startSync(Project $project, string $batchId): JsonResponse
    {
        $results = [];

        foreach (self::DOCUMENT_IDS as $docId) {
            $results[] = $this->generator->generate($project, $docId);
        }

        $allSucceeded = count($results) === count(self::DOCUMENT_IDS)
            && collect($results)->every(fn (array $result) => $result['status'] === 'done');

        if ($allSucceeded) {
            $project->setGate('B');
            $project->save();
        }

        return response()->json([
            'batch_id'   => $batchId,
            'project_id' => $project->id,
            'mode'       => 'sync',
            'total'      => count($results),
            'results'    => $results,
        ]);
    }

    private function streamInline(Project $project): StreamedResponse
    {
        $controller = $this;

        return new StreamedResponse(function () use ($project, $controller) {
            @set_time_limit(120);

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
        }, 200, $this->sseHeaders());
    }

    /* -------------------------------------------------------------- */
    /*  Internal                                                      */
    /* -------------------------------------------------------------- */

    private function queueMode(): bool
    {
        return config('ai.generation.mode', 'queue') === 'queue';
    }

    /**
     * @return array<string,string>
     */
    private function sseHeaders(): array
    {
        return [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];
    }

    /**
     * Guard: state wizard minimal harus sudah ada intake.
     */
    private function guardWizardComplete(Project $project): ?JsonResponse
    {
        $state = $project->draft_state ?? [];

        if (empty($state['intake']['project_name'])) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_FAILED',
                    'message' => 'Wizard belum lengkap. Selesaikan Step 1-4 terlebih dahulu.',
                ],
            ], 422);
        }

        return null;
    }

    /**
     * Rekap progres untuk polling UI.
     *
     * @return array<string,mixed>
     */
    private function progressSnapshot(Project $project): array
    {
        $documents = (array) (($project->draft_state ?? [])['documents'] ?? []);

        // Status terakhir per doc_id (ai_jobs bisa punya beberapa baris karena retry).
        $latest = [];
        $jobs = AiJob::where('project_id', $project->id)
            ->orderBy('created_at')
            ->get(['doc_id', 'status', 'error_message', 'token_in', 'token_out', 'latency_ms']);

        foreach ($jobs as $job) {
            $latest[$job->doc_id] = $job;
        }

        $docs = [];
        $counts = ['queued' => 0, 'running' => 0, 'done' => 0, 'failed' => 0, 'cancelled' => 0, 'pending' => 0];
        $tokensIn = 0;
        $tokensOut = 0;

        foreach (self::DOCUMENT_IDS as $docId) {
            $job = $latest[$docId] ?? null;
            $hasContent = isset($documents[$docId]) && trim((string) $documents[$docId]) !== '';
            $status = $job?->status ?? 'pending';

            // Dokumen sudah tersimpan tapi ai_job tercatat lain → percayai konten.
            if ($hasContent && $status !== 'done') {
                $status = 'done';
            }

            $counts[$status] = ($counts[$status] ?? 0) + 1;
            $tokensIn += (int) ($job->token_in ?? 0);
            $tokensOut += (int) ($job->token_out ?? 0);

            $docs[] = [
                'doc_id'     => $docId,
                'status'     => $status,
                'chars'      => $hasContent ? mb_strlen((string) $documents[$docId]) : 0,
                'error'      => $job->error_message ?? null,
                'latency_ms' => (int) ($job->latency_ms ?? 0),
            ];
        }

        $total = count(self::DOCUMENT_IDS);

        return [
            'project_id' => $project->id,
            'provider'   => $this->aiProvider->name(),
            'mode'       => $this->queueMode() ? 'queue' : 'sync',
            'gate'       => $project->current_gate,
            'total'      => $total,
            'counts'     => $counts,
            'finished'   => $counts['done'] + $counts['failed'] + $counts['cancelled'] >= $total,
            'tokens_in'  => $tokensIn,
            'tokens_out' => $tokensOut,
            'documents'  => $docs,
        ];
    }
}
