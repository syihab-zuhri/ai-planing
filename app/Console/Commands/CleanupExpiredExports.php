<?php

namespace App\Console\Commands;

use App\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Hapus file ZIP dan row DB untuk exports yang expired > 24 jam.
 */
class CleanupExpiredExports extends Command
{
    protected $signature = 'cleanup:exports';

    protected $description = 'Remove expired export ZIP files and their database rows (expired > 24h)';

    public function handle(): int
    {
        $cutoff = now()->subHours(24);

        $exports = Export::where('expires_at', '<', $cutoff)->get();

        if ($exports->isEmpty()) {
            $this->info('No expired exports found.');

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($exports as $export) {
            // Delete the ZIP file from local disk
            if ($export->file_path && Storage::disk('local')->exists($export->file_path)) {
                Storage::disk('local')->delete($export->file_path);
            }

            $export->delete();
            $deleted++;
        }

        $this->info("Cleaned up {$deleted} expired export(s).");

        return self::SUCCESS;
    }
}
