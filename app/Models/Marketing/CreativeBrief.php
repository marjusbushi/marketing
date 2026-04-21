<?php

namespace App\Models\Marketing;

use App\Models\DailyBasketPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Creative Brief — the record that bridges AI, editor, and post.
 *
 * See the migration class docblock for the L1 vs L2 fill strategy. The
 * model enforces the relationship to RenderJob manually because the FK
 * lives the "wrong" direction (creative_briefs.render_job_id exists
 * without a DB constraint to avoid a circular FK with marketing_render_jobs).
 */
class CreativeBrief extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'marketing_creative_briefs';

    protected $fillable = [
        'daily_basket_post_id',
        'template_id',
        'post_type',
        'aspect',
        'duration_sec',
        'caption_sq',
        'caption_en',
        'hashtags',
        'music_id',
        'script',
        'media_slots',
        'suggested_time',
        'source',
        'ai_prompt_version',
        'state',
        'render_job_id',
        'created_by',
    ];

    protected $casts = [
        'hashtags'       => 'array',
        'script'         => 'array',
        'media_slots'    => 'array',
        'state'          => 'array',
        'suggested_time' => 'datetime',
    ];

    public function dailyBasketPost(): BelongsTo
    {
        return $this->belongsTo(DailyBasketPost::class, 'daily_basket_post_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function renderJobs(): HasMany
    {
        return $this->hasMany(RenderJob::class, 'creative_brief_id');
    }

    public function latestRenderJob(): BelongsTo
    {
        // Pseudo-belongsTo via the unconstrained FK column; resolves at query time.
        return $this->belongsTo(RenderJob::class, 'render_job_id');
    }

    public function scopeBySource(Builder $q, string $source): Builder
    {
        return $q->where('source', $source);
    }

    public function scopeOfType(Builder $q, string $postType): Builder
    {
        return $q->where('post_type', $postType);
    }
}
