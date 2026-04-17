<?php

namespace App\Models\TikTok;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A TikTok Ads campaign.
 *
 * @property int         $id
 * @property string      $campaign_id
 * @property string|null $advertiser_id
 * @property string      $name
 * @property string|null $objective
 * @property string|null $status
 * @property float|null  $budget
 * @property string|null $budget_mode
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class TikTokCampaign extends Model
{
    // TikTok data lives in the DIS database (shared with the monolith).
    protected $connection = 'dis';

    protected $table = 'tiktok_campaigns';

    protected $fillable = [
        'campaign_id',
        'advertiser_id',
        'name',
        'objective',
        'status',
        'budget',
        'budget_mode',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                         */
    /* ------------------------------------------------------------------ */

    public function insights(): HasMany
    {
        return $this->hasMany(TikTokAdsInsight::class);
    }
}
