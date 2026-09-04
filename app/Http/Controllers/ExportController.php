<?php

namespace App\Http\Controllers;

use App\Models\Export;
use App\Models\Project;
use App\Services\WizardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

/**
 * ExportController — endpoint ZIP export (PRD/EXPORT.md).
 *
 * Acuan:
 *   - API.md §3 (API-EXPORT-START, API-EXPORT-DOWNLOAD).
 *   - PRD/EXPORT.md §7 (struktur folder) + §9 (Gate requirement).
 *
 * Phase 1 backend:
 *   - start(): kumpulkan dokumen dari draft_state, tulis ke file ZIP,
 *     generate token + URL, simpan ke tabel `exports`.
 *   - download(): lookup token, cek expiry, stream ZIP.
 */
class ExportController extends Controller
{
    /**
     * TTL signed URL dalam menit (ENV: EXPORT_SIGNED_URL_TTL_MINUTES, default 60).
     */
    private function ttlMinutes(): int
    {
        return (int) env('EXPORT_SIGNED_URL_TTL_MINUTES', 60);
    }

    public function __construct(
        private readonly WizardService $wizard,
    ) {
    }

    /**
     * API-EXPORT-START (POST /api/export/start).
     */
    public function start(Request $request): JsonResponse
    {
        $sessionId = $request->session()->getId();
        $project = $this->wizard->getState($sessionId);
        if (!$project) {
            return response()->json(['error' => ['code' => 'VALIDATION_FAILED', 'message' => 'Project not found']], 404);
        }

        // Gate requirement: B minimum (PRD/EXPORT.md §5 + BR-VALID-001).
        $currentGate = $project->current_gate ?? 'A';
        if (!in_array($currentGate, ['B', 'C', 'D'], true)) {
            return response()->json([
                'error' => [
                    'code'    => 'GATE_INSUFFICIENT',
                    'message' => 'Gate A tidak cukup untuk export',
                    'details' => [
                        'current_gate'  => $currentGate,
                        'required_gate' => 'B',
                    ],
                ],
            ], 403);
        }

        // Bangun ZIP.
        $state = $project->draft_state ?? [];
        $documents = (array) ($state['documents'] ?? []);

        if (empty($documents)) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_FAILED',
                    'message' => 'Belum ada dokumen. Generate terlebih dahulu.',
                ],
            ], 422);
        }

        $zipRelativePath = $this->writeZip($project, $documents, $state);

        $token = Str::random(48);
        $expires = now()->addMinutes($this->ttlMinutes());

        $export = Export::create([
            'project_id'    => $project->id,
            'file_path'     => $zipRelativePath,
            'file_size'     => filesize(Storage::disk('local')->path($zipRelativePath)) ?: 0,
            'download_token'=> $token,
            'expires_at'    => $expires,
        ]);

        \Log::info('export.started', [
            'project_id' => $project->id,
            'export_id'  => $export->id,
            'file_size'  => $export->file_size,
        ]);

        return response()->json([
            'export_id'    => $export->id,
            'download_url' => "/api/export/download/{$token}",
            'expires_at'   => $expires->toIso8601String(),
        ]);
    }

    /**
     * API-EXPORT-DOWNLOAD (GET /api/export/download/{token}).
     */
    public function download(string $token): Response
    {
        $export = Export::where('download_token', $token)->first();
        if (!$export) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_FAILED',
                    'message' => 'Token tidak valid.',
                ],
            ], 404);
        }

        if ($export->isExpired()) {
            return response()->json([
                'error' => [
                    'code'    => 'VALIDATION_FAILED',
                    'message' => 'Link download sudah kadaluarsa.',
                ],
            ], 403);
        }

        $absPath = Storage::disk('local')->path($export->file_path);
        if (!is_file($absPath)) {
            return response()->json([
                'error' => [
                    'code'    => 'INTERNAL',
                    'message' => 'File tidak ditemukan di storage.',
                ],
            ], 500);
        }

        \Log::info('export.completed', [
            'export_id'  => $export->id,
            'project_id' => $export->project_id,
        ]);

        return new BinaryFileResponse($absPath, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="blueprint-' . $export->project_id . '.zip"',
        ]);
    }

    /* -------------------------------------------------------------- */
    /*  Internal                                                     */
    /* -------------------------------------------------------------- */

    /**
     * Tulis dokumen ke ZIP di storage/app/exports/{project_id}.zip.
     * Return relative path dari disk 'local'.
     */
    private function writeZip(Project $project, array $documents, array $state): string
    {
        $zipName = "exports/{$project->id}.zip";
        $absPath = Storage::disk('local')->path($zipName);

        // Pastikan direktori ada.
        Storage::disk('local')->makeDirectory('exports');

        $zip = new ZipArchive();
        $opened = $zip->open($absPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new \RuntimeException("Tidak bisa membuat ZIP: code={$opened}");
        }

        foreach ($documents as $path => $content) {
            $zip->addFromString($path, (string) $content);
        }

        // OVERRIDE.md jika ada overrides.
        if (!empty($state['overrides'])) {
            $overrideContent = "# Override Log\n\n";
            foreach ($state['overrides'] as $ov) {
                $overrideContent .= "## Override {$ov['gate']} ({$ov['created_at']})\n";
                $overrideContent .= "- Reason: {$ov['reason']}\n";
                $overrideContent .= "- ID: {$ov['id']}\n\n";
            }
            $zip->addFromString('OVERRIDE.md', $overrideContent);
        }

        $zip->close();

        return $zipName;
    }
}