<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArchitectureRequest;
use App\Http\Requests\StoreDomainRequest;
use App\Http\Requests\StoreIntakeRequest;
use App\Http\Requests\StoreScopeRequest;
use App\Services\WizardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * WizardController — endpoint API wizard (API-WIZARD-*).
 *
 * Lokasi URL: /api/wizard/* (routes/api.php).
 * Session: session()->getId() dipakai sebagai session_id untuk project lookup.
 */
class WizardController extends Controller
{
    public function __construct(
        private readonly WizardService $wizard,
    ) {
    }

    /**
     * API-WIZARD-START — buat proyek baru (POST /api/wizard/start).
     */
    public function start(Request $request): JsonResponse
    {
        $sessionId = $this->sessionId($request);
        $project = $this->wizard->createProject($sessionId);

        return response()->json([
            'project_id' => $project->id,
            'redirect'   => '/wizard/step/intake',
        ]);
    }

    /**
     * API-WIZARD-STATE — load state wizard untuk session saat ini (GET /api/wizard/state).
     */
    public function state(Request $request): JsonResponse
    {
        $sessionId = $this->sessionId($request);
        $project = $this->wizard->getState($sessionId);

        if (!$project) {
            // Kembalikan state kosong dengan project_id = null agar UI tahu
            // wizard belum dimulai (per API.md: 200 OK with null project_id).
            return response()->json([
                'project_id'    => null,
                'current_gate'  => null,
                'draft_state'   => [
                    'intake'         => null,
                    'domain'         => null,
                    'scope'          => null,
                    'architecture'   => null,
                    'clarifications' => [],
                ],
            ]);
        }

        return response()->json([
            'project_id'   => $project->id,
            'current_gate' => $project->current_gate,
            'draft_state'  => $project->draft_state,
        ]);
    }

    /**
     * API-WIZARD-INTAKE (POST /api/wizard/intake).
     */
    public function intake(StoreIntakeRequest $request): JsonResponse
    {
        $sessionId = $this->sessionId($request);
        $project = $this->wizard->saveIntake($sessionId, $request->intakeData());

        return response()->json([
            'project_id' => $project->id,
            'step'       => 'intake',
            'saved_at'   => now()->toIso8601String(),
            'next'       => '/wizard/step/domain',
        ]);
    }

    /**
     * API-WIZARD-DOMAIN (POST /api/wizard/domain).
     */
    public function domain(StoreDomainRequest $request): JsonResponse
    {
        $sessionId = $this->sessionId($request);
        $project = $this->wizard->saveDomain($sessionId, $request->domainData());

        return response()->json([
            'project_id' => $project->id,
            'step'       => 'domain',
            'saved_at'   => now()->toIso8601String(),
            'next'       => '/wizard/step/scope',
        ]);
    }

    /**
     * API-WIZARD-SCOPE (POST /api/wizard/scope).
     */
    public function scope(StoreScopeRequest $request): JsonResponse
    {
        $sessionId = $this->sessionId($request);
        $project = $this->wizard->saveScope($sessionId, $request->scopeData());

        return response()->json([
            'project_id' => $project->id,
            'step'       => 'scope',
            'saved_at'   => now()->toIso8601String(),
            'next'       => '/wizard/step/architecture',
        ]);
    }

    /**
     * API-WIZARD-ARCHITECTURE (POST /api/wizard/architecture).
     */
    public function architecture(StoreArchitectureRequest $request): JsonResponse
    {
        $sessionId = $this->sessionId($request);
        $project = $this->wizard->saveArchitecture($sessionId, $request->architectureData());

        return response()->json([
            'project_id' => $project->id,
            'step'       => 'architecture',
            'saved_at'   => now()->toIso8601String(),
            'next'       => '/wizard/step/clarify',
        ]);
    }

    /* -------------------------------------------------------------- */
    /*  UI endpoints (web routes)                                     */
    /* -------------------------------------------------------------- */

    /**
     * Landing page (GET /).
     */
    public function landing()
    {
        return view('wizard.landing');
    }

    /**
     * Wizard frame (GET /wizard).
     * Menampilkan step default 'intake'.
     */
    public function wizard()
    {
        return view('wizard.frame', ['step' => 'intake']);
    }

    /**
     * Step view (GET /wizard/step/{step}).
     */
    public function step(string $step)
    {
        $allowed = ['intake', 'domain', 'scope', 'architecture', 'clarify'];
        if (!in_array($step, $allowed, true)) {
            abort(404);
        }
        return view('wizard.frame', ['step' => $step]);
    }

    /**
     * Halaman about (GET /about).
     */
    public function about()
    {
        return view('about');
    }

    /* -------------------------------------------------------------- */
    /*  Helpers                                                      */
    /* -------------------------------------------------------------- */

    /**
     * Ambil session_id. Pastikan session sudah di-start (Laravel 11 default
     * sudah start untuk web & API middleware groups).
     */
    private function sessionId(Request $request): string
    {
        if (!$request->hasSession()) {
            $request->setLaravelSession(app('session.store'));
        }
        return $request->session()->getId();
    }
}