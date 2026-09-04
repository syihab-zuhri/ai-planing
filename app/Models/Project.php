<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent model untuk tabel `projects`.
 *
 * Acuan: ERD.md §2.
 */
class Project extends Model
{
    use HasUuids;

    protected $table = 'projects';

    /**
     * Mass-assignable fields — hanya yang aman diisi dari controller.
     */
    protected $fillable = [
        'session_id',
        'draft_state',
        'current_gate',
        'last_activity_at',
    ];

    /**
     * Casting otomatis.
     */
    protected $casts = [
        'draft_state'      => 'array',
        'last_activity_at' => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Refresh timestamp aktivitas (dipakai oleh middleware cleanup 24 jam).
     */
    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->save();
    }

    /**
     * Naikkan gate jika memenuhi syarat. Default toleran: hanya naik, tidak turun.
     */
    public function setGate(string $gate): void
    {
        $allowed = ['A', 'B', 'C', 'D'];
        if (!in_array($gate, $allowed, true)) {
            return;
        }
        $this->current_gate = $gate;
    }

    public function aiJobs(): HasMany
    {
        return $this->hasMany(AiJob::class, 'project_id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(Export::class, 'project_id');
    }
}