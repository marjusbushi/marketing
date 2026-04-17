<?php

namespace App\Models\Meta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily aggregated Meta Ads performance metrics.
 *
 * @property int         $id
 * @property int|null    $meta_ad_account_id
 * @property int|null    $meta_campaign_id
 * @property int|null    $meta_ad_set_id
 * @property \Carbon\Carbon $date
 * @property int|null    $impressions
 * @property int|null    $reach
 * @property int|null    $clicks
 * @property float|null  $spend
 * @property int|null    $post_engagement
 * @property int|null    $page_engagement
 * @property int|null    $link_clicks
 * @property int|null    $video_views
 * @property int|null    $purchases
 * @property float|null  $purchase_value
 * @property int|null    $add_to_cart
 * @property int|null    $initiate_checkout
 * @property int|null    $leads
 * @property int|null    $messaging_conversations
 * @property int|null    $messaging_conversations_replied
 * @property array|null  $age_breakdown
 * @property array|null  $gender_breakdown
 * @property array|null  $platform_breakdown
 * @property array|null  $placement_breakdown
 * @property \Carbon\Carbon|null $synced_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class MetaAdsInsight extends Model
{
    // Meta data lives in the DIS database (shared with the monolith).
    protected $connection = 'dis';

    protected $table = 'meta_ads_insights';

    protected $fillable = [
        'meta_ad_account_id',
        'meta_campaign_id',
        'meta_ad_set_id',
        'date',
        'impressions',
        'reach',
        'clicks',
        'spend',
        'post_engagement',
        'page_engagement',
        'link_clicks',
        'video_views',
        'purchases',
        'purchase_value',
        'add_to_cart',
        'initiate_checkout',
        'leads',
        'messaging_conversations',
        'messaging_conversations_replied',
        'age_breakdown',
        'gender_breakdown',
        'platform_breakdown',
        'placement_breakdown',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date'                 => 'date',
            'spend'                => 'decimal:4',
            'age_breakdown'        => 'array',
            'gender_breakdown'     => 'array',
            'platform_breakdown'   => 'array',
            'placement_breakdown'  => 'array',
            'synced_at'            => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                         */
    /* ------------------------------------------------------------------ */

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MetaCampaign::class);
    }

    public function adSet(): BelongsTo
    {
        return $this->belongsTo(MetaAdSet::class);
    }
}
