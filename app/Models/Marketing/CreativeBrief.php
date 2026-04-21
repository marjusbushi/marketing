<?php

namespace App\Models\Marketing;

use App\Models\DailyBasketPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Creative Brief — the record that bridges AI, editor, and post.
 *
 * Post Decision #14: the render-job relationship is gone. Video work
 * happens in CapCut (manual upload into `media_slots`) and photo work
 * in Canva (the Canva design id + exported asset URL land in `state`
 * under the `canva` key). Neither path needs an internal render queue.
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

    public function scopeBySource(Builder $q, string $source): Builder
    {
        return $q->where('source', $source);
    }

    public function scopeOfType(Builder $q, string $postType): Builder
    {
        return $q->where('post_type', $postType);
    }
}
