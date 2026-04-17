<?php

namespace App\Models\Meta;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Period-level reach for a Meta Ad Account (28-day windows, etc.).
 *
 * @property int         $id
 * @property int         $meta_ad_account_id
 * @property \Carbon\Carbon $date_from
 * @property \Carbon\Carbon $date_to
 * @property int|null    $reach
 * @property \Carbon\Carbon|null $synced_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class MetaAdsPeriodReach extends Model
{
    // Meta data lives in the DIS database (shared with the monolith).
    protected $connection = 'dis';

    protected $table = 'meta_ads_period_reach';

    protected $fillable = [
        'meta_ad_account_id',
        'date_from',
        'date_to',
        'reach',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to'   => 'date',
            'synced_at'  => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Relations                                                         */
    /* ------------------------------------------------------------------ */

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(MetaAdAccount::class);
    }
}
