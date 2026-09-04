<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\WizardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ValidateController — endpoint validator Gate A/B/C/D.
 *
 * Acuan: PRD/VALIDATION.md + API.md §3.
 *
 * Phase 1 (Backend): implementasi sederhana.
 *   - Gate A : intake + domain lengkap.
 *   - Gate B : scope + architecture + minimal 1 P0 feature, ≥ 1 dokumen di-generate.
 *   - Gate C : semua 18 dokumen ada, content minimal 200 char, tidak ada placeholder.
 *   - Gate D : Gate C + semua dokumen punya heading level 1.
 */
class ValidateController extends Controller
{
    public function __construct(
        private readonly WizardService $wizard,
    ) {
    }

    /**
     * API-VALIDATE-RUN (POST /api/validate/run).
     */
    public function run(Request $request): JsonResponse
    {
        $sessionId = $request->session()->getId();
        $project = $this->wizard->getState($sessionId);
        if (!$project) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_FAILED',
                    'message' => 'Belum ada proyek. Mulai wizard terlebih dahulu.',
                ],
            ], 404);
        }

        $result = $this->evaluate($project);

        // Simpan ke draft_state.validation (ERD.md §6).
        $state = $project->draft_state ?? [];
        $state['validation'] = $result;
        $project->draft_state = $state;
        $project->setGate($result['gate']);
        $project->save();

        return response()->json($result);
    }

    /**
     * API-VALIDATE-STATUS (GET /api/validate/status).
     */
    public function status(Request $request): JsonResponse
    {
        $sessionId = $request->session()->getId();
        $project = $this->wizard->getState($sessionId);
        if (!$project) {
            return response()->json([
                'gate'    => null,
                'last_run'=> null,
                'message' => 'Belum ada proyek.',
            ]);
        }

        $validation = ($project->draft_state ?? [])['validation'] ?? null;
        if (!$validation) {
            // Auto-run agar UI langsung dapat hasil.
            $validation = $this->evaluate($project);
            $state = $project->draft_state ?? [];
            $state['validation'] = $validation;
            $project->draft_state = $state;
            $project->setGate($validation['gate']);
            $project->save();
        }

        return response()->json($validation);
    }

    /**
     * API-VALIDATE-OVERRIDE (POST /api/validate/override).
     */
    public function override(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gate'   => ['required', 'string', 'in:C,D'],
            'reason' => ['required', 'string', 'min:20'],
        ]);

        $sessionId = $request->session()->getId();
        $project = $this->wizard->getState($sessionId);
        if (!$project) {
            return response()->json(['error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Project not found']], 404);
        }

        // BR-VALID-004: Gate B TIDAK boleh di-override. Sudah di-block via validation rule 'in:B,C,D'.
        $overrideId = (string) \Illuminate\Support\Str::uuid();
        $state = $project->draft_state ?? [];
        $state['overrides'] = $state['overrides'] ?? [];
        $state['overrides'][] = [
            'id'         => $overrideId,
            'gate'       => $validated['gate'],
            'reason'     => substr($validated['reason'], 0, 500),
            'created_at' => now()->toIso8601String(),
        ];
        $project->draft_state = $state;
        $project->save();

        \Log::info('validation.override', [
            'project_id'    => $project->id,
            'gate'          => $validated['gate'],
            'reason_length' => strlen($validated['reason']),
        ]);

        return response()->json([
            'gate'        => $validated['gate'],
            'overridden'  => true,
            'override_id' => $overrideId,
        ]);
    }

    /* -------------------------------------------------------------- */
    /*  Validator logic                                               */
    /* -------------------------------------------------------------- */

    /**
     * Evaluasi Gate A/B/C/D. Kembalikan struktur konsisten.
     */
    private function evaluate(Project $project): array
    {
        $state = $project->draft_state ?? [];
        $passed = [];
        $warnings = [];
        $blockers = [];

        // ---- Structural completeness (selalu) ----
        $intake = $state['intake'] ?? null;
        $domain = $state['domain'] ?? null;
        $scope  = $state['scope']  ?? null;
        $arch   = $state['architecture'] ?? null;

        $intakeComplete = $intake && !empty($intake['project_name']) && !empty($intake['project_goal']);
        if ($intakeComplete) {
            $passed[] = 'structural: intake';
        } else {
            $blockers[] = 'Intake belum lengkap (project_name dan project_goal wajib).';
        }

        $domainComplete = $domain && !empty($domain['domain_category']) && !empty($domain['problem_statement']);
        if ($domainComplete) {
            $passed[] = 'structural: domain';
        } else {
            $blockers[] = 'Domain belum lengkap.';
        }

        $scopeComplete = $scope && !empty($scope['p0_features']);
        if ($scopeComplete) {
            $passed[] = 'structural: scope';
        } else {
            $blockers[] = 'Scope belum memiliki minimal 1 fitur P0.';
        }

        $archComplete = $arch && !empty($arch['preferred_stack']) && !empty($arch['data_sensitivity']);
        if ($archComplete) {
            $passed[] = 'structural: architecture';
        } else {
            $blockers[] = 'Architecture direction belum dipilih.';
        }

        // ---- Content checks ----
        $documents = (array) ($state['documents'] ?? []);
        $requiredDocuments = GenerateController::DOCUMENT_IDS;
        $missingDocuments = array_values(array_diff($requiredDocuments, array_keys($documents)));
        $hasAllDocuments = $missingDocuments === [];
        if ($hasAllDocuments) {
            $passed[] = 'content: all_documents_generated';
        } elseif ($documents !== []) {
            $blockers[] = 'Dokumen belum lengkap: '.count($missingDocuments).' dokumen wajib belum tersedia.';
        } else {
            $warnings[] = 'Belum ada dokumen di-generate. Jalankan /api/generate/start.';
        }

        foreach ($documents as $docId => $content) {
            if (!in_array($docId, $requiredDocuments, true)) {
                $blockers[] = "Dokumen tidak dikenal: {$docId}.";
                continue;
            }
            if (!is_string($content) || strlen(trim($content)) < 200) {
                $warnings[] = "Dokumen {$docId} terlalu pendek (<200 char).";
            }
            if (preg_match('/\{\{[^}]+\}\}/', (string) $content)) {
                $blockers[] = "Dokumen {$docId} masih berisi placeholder.";
            }
        }

        // ---- Determine gate ----
        $gate = 'A';
        if ($intakeComplete && $domainComplete) {
            $gate = 'A'; // baseline
        }
        if ($intakeComplete && $domainComplete && $scopeComplete && $archComplete) {
            $gate = 'B';
        }
        if (
            $gate === 'B' && $hasAllDocuments
            && count($blockers) === 0
        ) {
            $gate = 'C';
        }
        if (
            $gate === 'C'
            && $this->allDocsHaveHeading($documents)
        ) {
            $gate = 'D';
        }

        return [
            'gate'      => $gate,
            'last_run'  => now()->toIso8601String(),
            'passed'    => $passed,
            'warnings'  => $warnings,
            'blockers'  => $blockers,
        ];
    }

    private function allDocsHaveHeading(array $documents): bool
    {
        if (empty($documents)) {
            return false;
        }
        foreach ($documents as $content) {
            if (!preg_match('/^#\s+/m', (string) $content)) {
                return false;
            }
        }
        return true;
    }
}