<?php

namespace App\Http\Controllers;

use App\Services\WizardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ClarifyController — endpoint Step 5 (Clarification).
 *
 * Lokasi URL: /api/wizard/clarify/* (routes/api.php).
 *
 * Phase 1 (Backend) saat ini:
 *   - questions: kembalikan pertanyaan placeholder deterministik
 *     berdasarkan intake state (mock generator). Implementasi penuh dengan
 *     AiProviderInterface menyusul setelah OQ-001 resolved.
 *   - answers  : simpan jawaban ke draft_state.clarifications.
 */
class ClarifyController extends Controller
{
    public function __construct(
        private readonly WizardService $wizard,
    ) {
    }

    /**
     * API-WIZARD-CLARIFY-QUESTIONS (POST /api/wizard/clarify/questions).
     *
     * Phase 1 stub: hasilkan ≤ 5 pertanyaan berdasarkan kelengkapan intake.
     */
    public function questions(Request $request): JsonResponse
    {
        $sessionId = $request->session()->getId();
        $project = $this->wizard->getState($sessionId);

        if (!$project) {
            return response()->json([
                'questions'        => [],
                'skip_to_generate' => false,
                'message'          => 'Belum ada intake. Selesaikan Step 1-4 terlebih dahulu.',
            ], 200);
        }

        $state = $project->draft_state ?? [];
        $questions = $this->buildQuestions($state);

        // Jika intake sangat lengkap → skip flag (sesuai API.md §4).
        $skip = count($questions) === 0;

        return response()->json([
            'questions'        => $questions,
            'skip_to_generate' => $skip,
        ]);
    }

    /**
     * API-WIZARD-CLARIFY-ANSWERS (POST /api/wizard/clarify/answers).
     */
    public function answers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'answers'           => ['required', 'array'],
            'answers.*.id'      => ['required', 'string'],
            'answers.*.answer'  => ['required', 'string', 'max:1000'],
            'answers.*.label'   => ['nullable', 'string', 'in:ASSUMED,CONFIRMED'],
        ]);

        $sessionId = $request->session()->getId();

        // Inject label default jika tidak dikirim.
        $answers = array_map(function ($a) {
            $a['label'] = $a['label'] ?? 'CONFIRMED';
            return $a;
        }, $validated['answers']);

        $project = $this->wizard->saveClarifications($sessionId, $answers);

        return response()->json([
            'project_id'  => $project->id,
            'saved_count' => count($answers),
            'next'        => '/generate',
        ]);
    }

    /**
     * Bangun pertanyaan deterministik berdasarkan intake (Phase 1 stub).
     *
     * Aturan sederhana (placeholder, bukan AI call):
     *   - Selalu tanya stack jika architecture.preferred_stack kosong atau 'Saran sistem'.
     *   - Tanya hosting jika hosting_preference 'Saran sistem'.
     *   - Tanya P0 features minimum jika kosong.
     *   - Tanya data_sensitivity jika 'Restricted'.
     *   - Cap di 5 pertanyaan.
     */
    private function buildQuestions(array $state): array
    {
        $questions = [];
        $intake = $state['intake'] ?? [];
        $arch = $state['architecture'] ?? [];
        $scope = $state['scope'] ?? [];

        if (empty($intake['project_goal']) || strlen((string) $intake['project_goal']) < 30) {
            $questions[] = [
                'id'                 => 'ASM-001',
                'question'           => 'Apakah tujuan proyek benar-benar untuk audiens internal atau akan dipublikasikan?',
                'impact'             => 'scope',
                'default_suggestion' => 'Internal pilot untuk satu tim dulu',
                'confidence'         => 'Medium',
                'type'               => 'select',
                'options'            => ['Internal pilot', 'Publik B2C', 'B2B SaaS', 'Belum pasti'],
            ];
        }

        if (empty($arch['preferred_stack']) || ($arch['preferred_stack'] ?? '') === 'Saran sistem') {
            $questions[] = [
                'id'                 => 'ASM-002',
                'question'           => 'Stack apa yang Anda pilih bila sistem menyarankan?',
                'impact'             => 'biaya',
                'default_suggestion' => 'Laravel 11 + Blade + PostgreSQL',
                'confidence'         => 'High',
                'type'               => 'select',
                'options'            => ['Laravel 11 + Blade + PostgreSQL', 'Node + React + PostgreSQL', 'Python + FastAPI', 'Tidak ada preferensi'],
            ];
        }

        if (empty($scope['p0_features']) || count($scope['p0_features']) < 2) {
            $questions[] = [
                'id'                 => 'ASM-003',
                'question'           => 'Fitur minimum apa yang harus jalan di MVP pertama?',
                'impact'             => 'scope',
                'default_suggestion' => 'CRUD resource + autentikasi dasar',
                'confidence'         => 'Medium',
                'type'               => 'text',
            ];
        }

        if (($arch['data_sensitivity'] ?? '') === 'Restricted') {
            $questions[] = [
                'id'                 => 'ASM-004',
                'question'           => 'Bagaimana cara Anda menyimpan data Restricted?',
                'impact'             => 'security',
                'default_suggestion' => 'Encrypted at rest + audit log',
                'confidence'         => 'Low',
                'type'               => 'select',
                'options'            => ['Encrypted at rest + audit log', 'Hash only', 'Tokenisasi', 'Belum tahu'],
            ];
        }

        if (empty($intake['known_constraints'])) {
            $questions[] = [
                'id'                 => 'ASM-005',
                'question'           => 'Apakah ada constraint teknis/bisnis yang harus dipertimbangkan?',
                'impact'             => 'timeline',
                'default_suggestion' => 'Tidak ada, silakan pilih default',
                'confidence'         => 'Medium',
                'type'               => 'text',
            ];
        }

        // Cap di 5 (PRD/CLARIFICATION §2 US-CLARIFY-001).
        return array_slice($questions, 0, 5);
    }
}