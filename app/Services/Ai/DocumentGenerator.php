<?php

namespace App\Services\Ai;

use App\Models\AiJob;
use App\Models\Project;
use Illuminate\Support\Facades\Log;

/**
 * DocumentGenerator — satu-satunya tempat logika generate satu dokumen.
 *
 * Dipakai oleh:
 *   - App\Jobs\GenerateDocumentJob      (jalur produksi, via queue)
 *   - App\Http\Controllers\GenerateController::retry() (jalur satu dokumen)
 *
 * Alur (PRD/GENERATION.md §7, BR-GEN-003, BR-GEN-004):
 *   1. Bangun prompt via PromptBuilder.
 *   2. Panggil provider primary.
 *   3. Validasi Markdown. Bila gagal/malformed atau error retryable → ulangi 1x
 *      pada provider yang sama.
 *   4. Bila masih gagal → coba provider fallback (jika dikonfigurasi).
 *   5. Simpan hasil ke projects.draft_state.documents dan catat ai_jobs.
 */
class DocumentGenerator
{
    public function __construct(
        private readonly AiProviderInterface $primary,
        private readonly AiProviderResolver $resolver,
        private readonly PromptBuilder $promptBuilder,
        private readonly MarkdownValidator $validator,
    ) {
    }

    /**
     * Generate satu dokumen. Tidak pernah melempar exception — kegagalan
     * dikembalikan sebagai status 'failed' dan tercatat di ai_jobs.
     *
     * @return array{doc_id:string,status:string,provider?:string,tokens_in?:int,tokens_out?:int,latency_ms?:int,attempts?:int,error?:string}
     */
    public function generate(Project $project, string $docId, ?AiJob $job = null): array
    {
        $job ??= AiJob::create([
            'project_id' => $project->id,
            'doc_id'     => $docId,
            'provider'   => $this->primary->name(),
            'status'     => 'running',
        ]);

        $job->update(['status' => 'running', 'provider' => $this->primary->name()]);

        $messages = $this->promptBuilder->build($project, $docId);
        $attempts = 0;
        $lastError = null;

        foreach ($this->providerPlan() as $provider) {
            $attempts++;

            try {
                $response = $provider->chat($messages);
                $content = $this->validator->sanitize($response->content);
                $check = $this->validator->validate($content);

                if (!$check['valid']) {
                    $lastError = $check['reason'];

                    Log::warning('ai_call.malformed', [
                        'provider' => $provider->name(),
                        'doc_id'   => $docId,
                        'reason'   => $check['reason'],
                        'attempt'  => $attempts,
                    ]);

                    $this->pause();
                    continue;
                }

                $this->persistDocument($project, $docId, $content);

                $job->update([
                    'status'       => 'done',
                    'provider'     => $provider->name(),
                    'token_in'     => $response->tokens_in,
                    'token_out'    => $response->tokens_out,
                    'latency_ms'   => $response->latency_ms,
                    'error_message'=> null,
                    'completed_at' => now(),
                ]);

                return [
                    'doc_id'     => $docId,
                    'status'     => 'done',
                    'provider'   => $provider->name(),
                    'tokens_in'  => $response->tokens_in,
                    'tokens_out' => $response->tokens_out,
                    'latency_ms' => $response->latency_ms,
                    'attempts'   => $attempts,
                ];
            } catch (AiProviderException $e) {
                $lastError = $e->getMessage();

                Log::warning('ai_call.failed', [
                    'provider'   => $provider->name(),
                    'doc_id'     => $docId,
                    'error_code' => $e->errorCode,
                    'retryable'  => $e->retryable,
                    'attempt'    => $attempts,
                ]);

                if (!$e->retryable) {
                    break;
                }

                $this->pause();
            } catch (\Throwable $e) {
                // Error tak terduga (mis. provider mock di test yang melempar).
                $lastError = $e->getMessage();

                Log::warning('ai_call.failed', [
                    'provider' => $provider->name(),
                    'doc_id'   => $docId,
                    'attempt'  => $attempts,
                ]);

                break;
            }
        }

        $message = $lastError ?? 'Generate gagal tanpa keterangan.';

        $job->update([
            'status'        => 'failed',
            'error_message' => mb_substr($message, 0, 500),
            'completed_at'  => now(),
        ]);

        return [
            'doc_id'   => $docId,
            'status'   => 'failed',
            'attempts' => $attempts,
            'error'    => mb_substr($message, 0, 200),
        ];
    }

    /**
     * Urutan percobaan: primary, primary (retry), lalu fallback bila ada.
     *
     * @return array<int,AiProviderInterface>
     */
    private function providerPlan(): array
    {
        $plan = [$this->primary];

        if (config('ai.generation.retry_on_malformed', true)) {
            $plan[] = $this->primary;
        }

        $fallback = $this->resolver->fallback();
        if ($fallback !== null) {
            $plan[] = $fallback;
        }

        return $plan;
    }

    /**
     * Simpan dokumen ke draft_state secara atomik terhadap baris project.
     * Di-refresh dulu supaya hasil job lain pada project yang sama tidak hilang.
     */
    private function persistDocument(Project $project, string $docId, string $content): void
    {
        $project->refresh();

        $state = $project->draft_state ?? [];
        $documents = (array) ($state['documents'] ?? []);
        $documents[$docId] = $content;
        $state['documents'] = $documents;

        $project->draft_state = $state;
        $project->save();
    }

    /**
     * Jeda antar percobaan (BR-GEN-002) — mengurangi risiko rate limit.
     */
    private function pause(): void
    {
        $delayMs = (int) config('ai.generation.batch_delay_ms', 500);

        if ($delayMs > 0 && !app()->runningUnitTests()) {
            usleep($delayMs * 1000);
        }
    }
}
