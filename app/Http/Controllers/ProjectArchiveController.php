<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class ProjectArchiveController extends Controller
{
    /**
     * Halaman Daftar Arsip Blueprint / Proyek yang sudah siap download dan pernah di-generate.
     */
    public function index(Request $request)
    {
        // Ambil project yang memiliki dokumen tersimpan
        $projects = Project::whereNotNull('draft_state->documents')
            ->orderBy('last_activity_at', 'desc')
            ->get()
            ->filter(function ($project) {
                $docs = $project->draft_state['documents'] ?? [];
                return is_array($docs) && count($docs) > 0;
            })
            ->map(function ($project) {
                $intake = $project->draft_state['intake'] ?? [];
                $domain = $project->draft_state['domain'] ?? [];
                $docs = $project->draft_state['documents'] ?? [];

                // Cek export terakhir
                $latestExport = $project->exports()->orderBy('expires_at', 'desc')->first();

                return [
                    'id'            => $project->id,
                    'name'          => $intake['project_name'] ?? 'Tanpa Judul',
                    'goal'          => $intake['project_goal'] ?? ($domain['problem_statement'] ?? 'Tidak ada deskripsi.'),
                    'gate'          => $project->current_gate,
                    'docs_count'    => count($docs),
                    'is_complete'   => count($docs) >= 23,
                    'has_export'    => $latestExport !== null && Storage::disk('local')->exists($latestExport->file_path),
                    'export_id'     => $latestExport?->id,
                    'file_size'     => $latestExport?->file_size ?? 0,
                    'last_activity' => $project->last_activity_at,
                ];
            });

        return view('archive.index', [
            'projects' => $projects,
        ]);
    }

    /**
     * Download langsung file ZIP arsip project (otomatis buat ZIP jika belum ada / expired).
     */
    public function downloadDirect(string $projectId): Response
    {
        $project = Project::findOrFail($projectId);
        $docs = $project->draft_state['documents'] ?? [];

        if (empty($docs)) {
            abort(404, 'Dokumen proyek belum tersedia.');
        }

        $zipRelativePath = "exports/{$project->id}.zip";
        $absPath = Storage::disk('local')->path($zipRelativePath);

        // Buat ZIP jika belum ada
        if (!Storage::disk('local')->exists($zipRelativePath)) {
            Storage::disk('local')->makeDirectory('exports');
            $zip = new ZipArchive();
            $opened = $zip->open($absPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($opened !== true) {
                abort(500, "Gagal membuat file ZIP arsip: {$opened}");
            }
            foreach ($docs as $path => $content) {
                $zip->addFromString($path, (string) $content);
            }
            $zip->close();
        }

        $safeName = Str::slug($project->draft_state['intake']['project_name'] ?? 'blueprint') ?: 'blueprint';

        return new BinaryFileResponse($absPath, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => 'attachment; filename="' . $safeName . '-' . substr($project->id, 0, 8) . '.zip"',
        ]);
    }
}
