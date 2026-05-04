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
    // TikTok data lives in the DIS database (shared with the monolith).
    protected $connection = 'dis';

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

    public static function start(string $syncType, string $dataType): self
    {
        return self::create([
            'sync_type'      => $syncType,
            'data_type'      => $dataType,
            'status'         => 'running',
            'records_synced' => 0,
            'records_failed' => 0,
            'api_calls_used' => 0,
        ]);
    }

    public function markSuccess(int $records, int $apiCalls): void
    {
        $this->update([
            'status'           => 'success',
            'records_synced'   => $records,
            'api_calls_used'   => $apiCalls,
            'duration_seconds' => $this->elapsedSeconds(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status'           => 'failed',
            'error_message'    => $error,
            'duration_seconds' => $this->elapsedSeconds(),
        ]);
    }

    private function elapsedSeconds(): int
    {
        return max(0, (int) ($this->created_at?->diffInSeconds(now()) ?? 0));
    }
}
