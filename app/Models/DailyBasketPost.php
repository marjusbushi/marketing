<?php

namespace App\Models;

use App\Enums\DailyBasketPostStage;
use App\Enums\DailyBasketPostType;
use App\Models\Content\ContentPost;
use App\Models\Dis\DisItemGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'target_platforms',
        'scheduled_for',
        'content_post_id',
        'assigned_to',
        'priority',
        'sort_order',
    ];

    protected $casts = [
        'target_platforms' => 'array',
        'scheduled_for'    => 'datetime',
        'stage'            => DailyBasketPostStage::class,
        'post_type'        => DailyBasketPostType::class,
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

    public function contentPost(): BelongsTo
    {
        return $this->belongsTo(ContentPost::class, 'content_post_id');
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
