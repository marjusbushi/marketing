<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Render Job — one render attempt for a Creative Brief's video output.
 *
 * The status transitions are: queued → rendering → (completed | failed).
 * Retries are handled by Horizon at the Job layer (tries=3); each job
 * row here records the final outcome.
 */
class RenderJob extends Model
{
    protected $table = 'marketing_render_jobs';

    protected $fillable = [
        'creative_brief_id',
        'status',
        'output_path',
        'output_thumbnail',
        'output_duration_seconds',
        'output_size_bytes',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const STATUS_QUEUED    = 'queued';
    public const STATUS_RENDERING = 'rendering';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';

    public function creativeBrief(): BelongsTo
    {
        return $this->belongsTo(CreativeBrief::class, 'creative_brief_id');
    }

    public function scopeInStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_QUEUED, self::STATUS_RENDERING]);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }
}
