<?php

namespace App\Models\TikTok;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Daily TikTok Ads performance metrics.
 *
 * @property int         $id
 * @property string|null $advertiser_id
 * @property int|null    $tiktok_campaign_id
 * @property \Carbon\Carbon $date
 * @property float|null  $spend
 * @property int|null    $impressions
 * @property int|null    $reach
 * @property int|null    $clicks
 * @property int|null    $video_views
 * @property int|null    $video_watched_2s
 * @property int|null    $video_watched_6s
 * @property int|null    $video_views_p25
 * @property int|null    $video_views_p50
 * @property int|null    $video_views_p75
 * @property int|null    $video_views_p100
 * @property float|null  $average_video_play
 * @property int|null    $likes
 * @property int|null    $comments
 * @property int|null    $shares
 * @property int|null    $follows
 * @property int|null    $profile_visits
 * @property int|null    $conversions
 * @property float|null  $cost_per_conversion
 * @property int|null    $purchases
 * @property float|null  $purchase_value
 * @property int|null    $add_to_cart
 * @property int|null    $initiate_checkout
 * @property int|null    $registrations
 * @property int|null    $landing_page_views
 * @property array|null  $age_breakdown
 * @property array|null  $gender_breakdown
 * @property array|null  $platform_breakdown
 * @property \Carbon\Carbon|null $synced_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class TikTokAdsInsight extends Model
{
    protected $table = 'tiktok_ads_insights';

    protected $fillable = [
        'advertiser_id',
        'tiktok_campaign_id',
        'date',
        'spend',
        'impressions',
        'reach',
        'clicks',
        'video_views',
        'video_watched_2s',
        'video_watched_6s',
        'video_views_p25',
        'video_views_p50',
        'video_views_p75',
        'video_views_p100',
        'average_video_play',
        'likes',
        'comments',
        'shares',
        'follows',
        'profile_visits',
        'conversions',
        'cost_per_conversion',
        'purchases',
        'purchase_value',
        'add_to_cart',
        'initiate_checkout',
        'registrations',
        'landing_page_views',
        'age_breakdown',
        'gender_breakdown',
        'platform_breakdown',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date'                => 'date',
            'spend'               => 'decimal:4',
            'age_breakdown'       => 'array',
            'gender_breakdown'    => 'array',
            'platform_breakdown'  => 'array',
            'synced_at'           => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                         */
    /* ------------------------------------------------------------------ */

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(TikTokCampaign::class);
    }
}
