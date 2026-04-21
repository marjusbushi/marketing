<?php

namespace App\Models\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Marketing Template — a reusable starting point for Creative Briefs.
 *
 * `engine` determines which editor opens: Polotno for static designs
 * (photo, carousel, story-static) and Remotion for video (reel, video).
 * `source` holds either a Polotno JSON document or a Remotion composition
 * identifier + default props.
 */
class Template extends Model
{
    protected $table = 'marketing_templates';

    protected $fillable = [
        'name',
        'slug',
        'kind',
        'engine',
        'source',
        'metadata',
        'thumbnail_path',
        'is_system',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'source'    => 'array',
        'metadata'  => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function briefs(): HasMany
    {
        return $this->hasMany(CreativeBrief::class, 'template_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForEngine(Builder $q, string $engine): Builder
    {
        return $q->where('engine', $engine);
    }

    public function scopeOfKind(Builder $q, string $kind): Builder
    {
        return $q->where('kind', $kind);
    }
}
