<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * WizardService — single source of truth untuk state wizard per-session.
 *
 * Tanggung jawab:
 *   - Create / load project by session_id.
 *   - Save per-step data (intake, domain, scope, architecture, clarifications)
 *     ke kolom `draft_state` (JSONB, struktur sesuai ERD.md §6).
 *   - Sanitize input sebelum disimpan (strip control chars, normalize whitespace).
 *   - Flag risiko prompt injection untuk di-review.
 *
 * State disimpan sebagai array di-cast JSON. ERD.md §6 mendefinisikan
 * struktur lengkap `draft_state`.
 */
class WizardService
{
    public function __construct(
        private readonly InputSanitizer $sanitizer,
        private readonly PromptInjectionDetector $injectionDetector,
    ) {
    }

    /**
     * Buat project baru (atau kembalikan yang sudah ada untuk session).
     * Mengikuti ERD.md §2 — session_id UNIQUE.
     */
    public function createProject(string $sessionId): Project
    {
        return Project::firstOrCreate(
            ['session_id' => $sessionId],
            [
                'id'               => (string) Str::uuid(),
                'draft_state'      => $this->emptyDraftState(),
                'current_gate'     => 'A',
                'last_activity_at' => now(),
            ],
        );
    }

    /**
     * Load state project untuk session. Null jika session belum pernah membuat project.
     */
    public function getState(string $sessionId): ?Project
    {
        return Project::where('session_id', $sessionId)->first();
    }

    /**
     * Pastikan session memiliki project. Berguna untuk endpoint state-only
     * yang ingin return project_id tanpa membuat baris baru.
     */
    public function getOrCreate(string $sessionId): Project
    {
        $project = $this->getState($sessionId);
        return $project ?? $this->createProject($sessionId);
    }

    /**
     * Step 1 — Intake. Field sesuai PRD/INTAKE.md §7.
     */
    public function saveIntake(string $sessionId, array $data): Project
    {
        $project = $this->getOrCreate($sessionId);

        $sanitized = [
            'project_name'      => $this->cleanShort($data['project_name'] ?? ''),
            'project_goal'      => $this->cleanLong($data['project_goal'] ?? ''),
            'target_users'      => $this->cleanLong($data['target_users'] ?? ''),
            'known_constraints' => isset($data['known_constraints']) && $data['known_constraints'] !== null && $data['known_constraints'] !== ''
                ? $this->cleanLong($data['known_constraints'])
                : null,
        ];

        $state = $project->draft_state ?? [];
        $state['intake'] = $sanitized;

        $project->draft_state = $state;
        $project->touchActivity();
        $project->save();

        $this->audit('wizard.step_completed', $project, ['step' => 'intake']);

        return $project;
    }

    /**
     * Step 2 — Domain.
     */
    public function saveDomain(string $sessionId, array $data): Project
    {
        $project = $this->getOrCreate($sessionId);

        $sanitized = [
            'domain_category'     => trim((string) ($data['domain_category'] ?? '')),
            'problem_statement'   => $this->cleanLong($data['problem_statement'] ?? ''),
            'value_proposition'   => $this->cleanMedium($data['value_proposition'] ?? ''),
            'scale_estimate_mvp'  => trim((string) ($data['scale_estimate_mvp'] ?? '')),
            'scale_estimate_12mo' => trim((string) ($data['scale_estimate_12mo'] ?? '')),
        ];

        $state = $project->draft_state ?? [];
        $state['domain'] = $sanitized;

        $project->draft_state = $state;
        $project->touchActivity();
        $project->save();

        $this->audit('wizard.step_completed', $project, ['step' => 'domain']);

        return $project;
    }

    /**
     * Step 3 — Scope. List item di-sanitize satu per satu.
     */
    public function saveScope(string $sessionId, array $data): Project
    {
        $project = $this->getOrCreate($sessionId);

        $sanitized = [
            'p0_features'  => $this->cleanList($data['p0_features'] ?? []),
            'p1_features'  => $this->cleanList($data['p1_features'] ?? []),
            'p2_features'  => $this->cleanList($data['p2_features'] ?? []),
            'out_of_scope' => $this->cleanList($data['out_of_scope'] ?? []),
        ];

        $state = $project->draft_state ?? [];
        $state['scope'] = $sanitized;

        $project->draft_state = $state;
        $project->touchActivity();
        $project->save();

        $this->audit('wizard.step_completed', $project, ['step' => 'scope']);

        return $project;
    }

    /**
     * Step 4 — Architecture.
     */
    public function saveArchitecture(string $sessionId, array $data): Project
    {
        $project = $this->getOrCreate($sessionId);

        $sanitized = [
            'preferred_stack'    => trim((string) ($data['preferred_stack'] ?? '')),
            'hosting_preference' => trim((string) ($data['hosting_preference'] ?? '')),
            'known_integrations' => $this->cleanList($data['known_integrations'] ?? []),
            'data_sensitivity'   => trim((string) ($data['data_sensitivity'] ?? '')),
        ];

        $state = $project->draft_state ?? [];
        $state['architecture'] = $sanitized;

        $project->draft_state = $state;
        $project->touchActivity();
        $project->save();

        $this->audit('wizard.step_completed', $project, ['step' => 'architecture']);

        return $project;
    }

    /**
     * Step 5 — Clarifications.
     * Format input: [{id, answer}] atau array asosiatif [question_id => answer].
     *
     * @param  array<int|string, mixed>  $answers
     */
    public function saveClarifications(string $sessionId, array $answers): Project
    {
        $project = $this->getOrCreate($sessionId);

        // Normalisasi ke array of objects sesuai ERD.md §6.
        $existing = ($project->draft_state ?? [])['clarifications'] ?? [];
        $merged = $this->mergeClarificationAnswers($existing, $answers);

        $state = $project->draft_state ?? [];
        $state['clarifications'] = $merged;

        $project->draft_state = $state;
        $project->touchActivity();
        $project->save();

        $this->audit('wizard.step_completed', $project, [
            'step'        => 'clarifications',
            'answer_count'=> count($answers),
        ]);

        return $project;
    }

    /**
     * Deteksi upaya prompt injection pada teks bebas. Dipakai controller
     * sebelum generate pertanyaan klarifikasi.
     */
    public function scanInjectionRisk(string $input): array
    {
        return $this->injectionDetector->detect($input);
    }

    /* -------------------------------------------------------------- */
    /*  Internal helpers                                              */
    /* -------------------------------------------------------------- */

    private function emptyDraftState(): array
    {
        return [
            'intake'         => null,
            'domain'         => null,
            'scope'          => null,
            'architecture'   => null,
            'clarifications' => [],
            'documents'      => new \stdClass(),
            'validation'     => null,
        ];
    }

    /**
     * Field pendek (≤ 80 char) — trim, strip control, tapi whitespace minimal
     * karena biasanya 1 baris (project_name).
     */
    private function cleanShort(string $input): string
    {
        return $this->sanitizer->stripControlChars(trim($input));
    }

    /**
     * Field medium (≤ 500) — full clean (sanitize + normalize whitespace).
     */
    private function cleanLong(string $input): string
    {
        return $this->sanitizer->clean($input);
    }

    /**
     * Field ≤ 300 (value_proposition).
     */
    private function cleanMedium(string $input): string
    {
        return $this->sanitizer->clean($input);
    }

    /**
     * List of strings. Tiap item di-trim + strip control chars.
     * Empty string difilter keluar.
     */
    private function cleanList(array $items): array
    {
        $cleaned = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }
            $value = $this->sanitizer->stripControlChars(trim($item));
            if ($value !== '') {
                $cleaned[] = $value;
            }
        }
        return $cleaned;
    }

    /**
     * Merge jawaban baru ke array klarifikasi yang sudah ada.
     *
     * @param  array<int,array<string,mixed>>  $existing
     * @param  array<int|string,mixed>         $answers
     * @return array<int,array<string,mixed>>
     */
    private function mergeClarificationAnswers(array $existing, array $answers): array
    {
        // Map existing by id untuk lookup cepat.
        $byId = [];
        foreach ($existing as $item) {
            if (isset($item['id'])) {
                $byId[$item['id']] = $item;
            }
        }

        foreach ($answers as $key => $value) {
            if (is_array($value) && isset($value['id'])) {
                // Format object {id, answer, label?, ...}.
                $id = $value['id'];
                $byId[$id] = array_merge($byId[$id] ?? [], $value);
                if (isset($value['answer'])) {
                    $byId[$id]['answer'] = $this->sanitizer->stripControlChars(
                        trim((string) $value['answer'])
                    );
                }
            } else {
                // Format key=>value (id => answer).
                $id = (string) $key;
                $byId[$id] = $byId[$id] ?? ['id' => $id];
                $byId[$id]['answer'] = $this->sanitizer->stripControlChars(
                    trim((string) $value)
                );
            }
        }

        // Index ulang (json_encode akan simpan numeric keys sebagai array).
        return array_values($byId);
    }

    /**
     * Audit log event (lihat SECURITY.md §11). Metadata saja, tanpa payload.
     */
    private function audit(string $event, Project $project, array $context = []): void
    {
        \Log::info($event, array_merge([
            'project_id' => $project->id,
            'session_id' => substr($project->session_id ?? '', 0, 8) . '***',
        ], $context));
    }
}