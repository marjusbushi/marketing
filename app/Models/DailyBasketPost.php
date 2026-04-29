<?php

namespace App\Models;

use App\Enums\DailyBasketPostStage;
use App\Enums\DailyBasketPostType;
use App\Models\Content\ContentPost;
use App\Models\Dis\DisItemGroup;
use App\Models\Marketing\CreativeBrief;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One post inside a Daily Basket.
 *
 * A post may feature 1+ products (see $itemGroups belongsToMany). It travels
 * through five stages (planning → production → editing → scheduling →
 * published). Once scheduled, it generates a linked ContentPost that the
 * existing publishing pipeline takes over.
 */
class DailyBasketPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'daily_basket_posts';

    protected $fillable = [
        'daily_basket_id',
        'post_type',
        'stage',
        'title',
        'reference_url',
        'reference_notes',
        'production_brief',
        'caption',
        'hashtags',
        'lokacioni',
        'modelet',
        'audienca',
        'target_platforms',
        'scheduled_for',
        'content_post_id',
        'creative_brief_id',
        'assigned_to',
        'claimed_by_user_id',
        'claimed_at',
        'priority',
        'sort_order',
    ];

    protected $casts = [
        'target_platforms' => 'array',
        'scheduled_for'    => 'datetime',
        'claimed_at'       => 'datetime',
        'stage'            => DailyBasketPostStage::class,
        'post_type'        => DailyBasketPostType::class,
    ];

    protected $appends = [
        'thumbnail_url',
        'first_media_url',
        'is_video',
        'reference_host',
    ];

    // ── Relationships ───────────────────────────────────────

    public function basket(): BelongsTo
    {
        return $this->belongsTo(DailyBasket::class, 'daily_basket_id');
    }

    public function itemGroups(): BelongsToMany
    {
        // Pivot lives in za_marketing; the related model lives in DIS.
        // The explicit pivot class pins pivot writes to the `mysql`
        // connection so Laravel doesn't try to insert into DIS.
        return $this->belongsToMany(
            DisItemGroup::class,
            'daily_basket_post_products',
            'daily_basket_post_id',
            'item_group_id',
        )
            ->using(DailyBasketPostProduct::class)
            ->withPivot(['sort_order', 'is_hero'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    public function contentPost(): BelongsTo
    {
        return $this->belongsTo(ContentPost::class, 'content_post_id');
    }

    public function creativeBrief(): BelongsTo
    {
        return $this->belongsTo(CreativeBrief::class, 'creative_brief_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(DailyBasketPostMedia::class, 'daily_basket_post_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    // ── Accessors ───────────────────────────────────────────

    /**
     * Static poster image for the first media — photo URL for images, or the
     * video's thumbnail poster when available. Videos without a poster path
     * return null so the caller can render a <video> tag instead of feeding
     * an .mp4 URL into an <img> (which would 404 and leave a blank tile).
     *
     * Only populated when the `media` relation is eager-loaded — otherwise
     * returns null rather than firing a per-post query.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->relationLoaded('media')) {
            return null;
        }

        $first = $this->media->first();
        if (! $first) {
            return null;
        }

        if ($first->is_video) {
            return $first->thumbnail_path
                ? \Illuminate\Support\Facades\Storage::disk($first->disk ?: 'public')->url($first->thumbnail_path)
                : null;
        }

        return $first->url;
    }

    /**
     * Raw first-media URL (video file or image) for callers that need the
     * actual asset — e.g. grid tile rendering which falls back to <video>
     * when there's no poster.
     */
    public function getFirstMediaUrlAttribute(): ?string
    {
        if (! $this->relationLoaded('media')) {
            return null;
        }
        return $this->media->first()?->url;
    }

    public function getIsVideoAttribute(): bool
    {
        if (! $this->relationLoaded('media')) {
            return false;
        }

        return (bool) $this->media->first()?->is_video;
    }

    /**
     * Host extracted from reference_url (without "www.") — used by the Post
     * Detail reference chip to render a favicon + readable source label
     * (e.g. "rocket.chat", "pinterest.com", "instagram.com").
     *
     * Returns null when the URL is empty or unparseable so the UI can fall
     * back to a generic link icon without special-casing empty strings.
     */
    public function getReferenceHostAttribute(): ?string
    {
        if (empty($this->reference_url)) {
            return null;
        }

        $host = parse_url($this->reference_url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./i', '', $host);
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopeInStage(Builder $q, DailyBasketPostStage|string $stage): Builder
    {
        $value = $stage instanceof DailyBasketPostStage ? $stage->value : $stage;

        return $q->where('stage', $value);
    }

    public function scopeScheduledBetween(Builder $q, string $from, string $to): Builder
    {
        return $q->whereBetween('scheduled_for', [$from, $to]);
    }
}
