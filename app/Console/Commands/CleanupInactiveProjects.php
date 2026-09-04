<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Hapus projects + relasi (ai_jobs, exports + file ZIP) yang last_activity_at > 7 hari.
 */
class CleanupInactiveProjects extends Command
{
    protected $signature = 'cleanup:projects';

    protected $description = 'Remove inactive projects (no activity > 7 days) and all related data';

    public function handle(): int
    {
        $cutoff = now()->subDays(7);

        $projects = Project::where('last_activity_at', '<', $cutoff)->get();

        if ($projects->isEmpty()) {
            $this->info('No inactive projects found.');

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($projects as $project) {
            // Delete export ZIP files from disk
            foreach ($project->exports as $export) {
                if ($export->file_path && Storage::disk('local')->exists($export->file_path)) {
                    Storage::disk('local')->delete($export->file_path);
                }
            }

            // Delete related exports and ai_jobs (then the project itself)
            $project->exports()->delete();
            $project->aiJobs()->delete();
            $project->delete();

            $deleted++;
        }

        $this->info("Cleaned up {$deleted} inactive project(s) and their related data.");

        return self::SUCCESS;
    }
}
