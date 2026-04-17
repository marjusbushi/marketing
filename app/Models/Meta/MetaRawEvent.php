<?php

namespace App\Models\Meta;

use Illuminate\Database\Eloquent\Model;

/**
 * Raw API request/response log for debugging and auditing.
 *
 * Uses only created_at (no updated_at) — events are immutable.
 *
 * @property int         $id
 * @property string|null $correlation_id
 * @property string      $endpoint
 * @property string      $method
 * @property string|null $token_type
 * @property array|null  $request_params
 * @property string|null $response_body
 * @property int|null    $http_status
 * @property int|null    $duration_ms
 * @property bool        $is_error
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $created_at
 */
class MetaRawEvent extends Model
{
    // Meta data lives in the DIS database (shared with the monolith).
    protected $connection = 'dis';

    public $timestamps = false;

    protected $table = 'meta_raw_events';

    protected $fillable = [
        'correlation_id',
        'endpoint',
        'method',
        'token_type',
        'request_params',
        'response_body',
        'http_status',
        'duration_ms',
        'is_error',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'request_params' => 'array',
            'is_error'       => 'boolean',
            'created_at'     => 'datetime',
        ];
    }
}
