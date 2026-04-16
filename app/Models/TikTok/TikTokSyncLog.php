<?php

namespace App\Models\TikTok;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit log for every TikTok data sync run.
 *
 * @property int         $id
 * @property string      $sync_type
 * @property string      $data_type
 * @property string      $status
 * @property int|null    $records_synced
 * @property int|null    $records_failed
 * @property int|null    $api_calls_used
 * @property string|null $error_message
 * @property array|null  $error_details
 * @property int|null    $duration_seconds
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class TikTokSyncLog extends Model
{
    protected $table = 'tiktok_sync_logs';

    protected $fillable = [
        'sync_type',
        'data_type',
        'status',
        'records_synced',
        'records_failed',
        'api_calls_used',
        'error_message',
        'error_details',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'error_details' => 'array',
        ];
    }
}
