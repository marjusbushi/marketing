<?php

namespace App\Models\Meta;

use Illuminate\Database\Eloquent\Model;

/**
 * Aggregated period totals per platform (e.g. 28-day rolling sums).
 *
 * @property int         $id
 * @property string      $platform
 * @property \Carbon\Carbon $date_from
 * @property \Carbon\Carbon $date_to
 * @property array|null  $metrics
 * @property \Carbon\Carbon|null $synced_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class MetaPeriodTotal extends Model
{
    // Meta data lives in the DIS database (shared with the monolith).
    protected $connection = 'dis';

    protected $table = 'meta_period_totals';

    protected $fillable = [
        'platform',
        'date_from',
        'date_to',
        'metrics',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to'   => 'date',
            'metrics'   => 'array',
            'synced_at'  => 'datetime',
        ];
    }
}
