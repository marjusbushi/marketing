<?php

namespace App\Models\Meta;

use Illuminate\Database\Eloquent\Model;

/**
 * Daily Facebook Page insights.
 *
 * @property int         $id
 * @property string      $page_id
 * @property \Carbon\Carbon $date
 * @property int|null    $page_impressions
 * @property int|null    $page_impressions_organic
 * @property int|null    $page_impressions_paid
 * @property int|null    $page_reach
 * @property int|null    $page_views_total
 * @property int|null    $page_post_engagements
 * @property int|null    $page_fans
 * @property int|null    $page_followers
 * @property int|null    $page_posts_impressions
 * @property int|null    $page_messages_new_threads
 * @property int|null    $page_video_views
 * @property int|null    $page_daily_follows
 * @property int|null    $page_daily_unfollows
 * @property int|null    $page_posts_impressions_paid
 * @property int|null    $page_posts_impressions_organic
 * @property int|null    $page_reactions_total
 * @property int|null    $page_reels_views
 * @property \Carbon\Carbon|null $synced_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class MetaPageInsight extends Model
{
    // Meta data lives in the DIS database (shared with the monolith).
    protected $connection = 'dis';

    protected $table = 'meta_page_insights';

    protected $fillable = [
        'page_id',
        'date',
        'page_impressions',
        'page_impressions_organic',
        'page_impressions_paid',
        'page_reach',
        'page_views_total',
        'page_post_engagements',
        'page_fans',
        'page_followers',
        'page_posts_impressions',
        'page_messages_new_threads',
        'page_video_views',
        'page_daily_follows',
        'page_daily_unfollows',
        'page_posts_impressions_paid',
        'page_posts_impressions_organic',
        'page_reactions_total',
        'page_reels_views',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date'      => 'date',
            'synced_at' => 'datetime',
        ];
    }
}
