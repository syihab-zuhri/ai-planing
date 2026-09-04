<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model untuk tabel `ai_jobs` (audit log setiap panggilan AI).
 *
 * Acuan: ERD.md §3.
 */
class AiJob extends Model
{
    use HasUuids;

    protected $table = 'ai_jobs';

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'doc_id',
        'provider',
        'status',
        'token_in',
        'token_out',
        'latency_ms',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}