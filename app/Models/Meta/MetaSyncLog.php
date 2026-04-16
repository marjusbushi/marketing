<?php

namespace App\Models\Meta;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit log for every Meta data sync run.
 *
 * @property int         $id
 * @property string      $sync_type
 * @property string      $data_type
 * @property \Carbon\Carbon|null $date_from
 * @property \Carbon\Carbon|null $date_to
 * @property string      $status
 * @property int|null    $records_synced
 * @property int|null    $records_failed
 * @property int|null    $api_calls_used
 * @property int         $retry_count
 * @property string|null $error_message
 * @property array|null  $error_details
 * @property int|null    $duration_seconds
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class MetaSyncLog extends Model
{
    protected $table = 'meta_sync_logs';

    protected $fillable = [
        'sync_type',
        'data_type',
        'date_from',
        'date_to',
        'status',
        'records_synced',
        'records_failed',
        'api_calls_used',
        'retry_count',
        'error_message',
        'error_details',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'date_from'     => 'date',
            'date_to'       => 'date',
            'error_details' => 'array',
        ];
    }
}
