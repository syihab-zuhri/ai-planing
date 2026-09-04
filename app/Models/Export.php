<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model untuk tabel `exports` (track file ZIP export).
 *
 * Acuan: ERD.md §4.
 */
class Export extends Model
{
    use HasUuids;

    protected $table = 'exports';

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'file_path',
        'file_size',
        'download_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? true;
    }
}