<?php

namespace App\Models\Meta;

use Illuminate\Database\Eloquent\Model;

/**
 * A single Instagram DM event captured via Meta webhook.
 *
 * Table lives on the DIS database but the WRITE path is here in za-marketing
 * (since marketing.zeroabsolute.dev is the public domain that receives the
 * webhook). Reads happen from both sides; writes only from the webhook Job.
 *
 * @property int         $id
 * @property string      $message_id
 * @property string      $thread_id
 * @property string      $from_id
 * @property string      $page_id
 * @property bool        $is_from_page
 * @property \Carbon\Carbon $received_at
 * @property string|null $ad_id
 * @property bool        $is_first_of_thread
 * @property string      $platform
 * @property array|null  $raw_payload
 */
class MetaIgDmEvent extends Model
{
    protected $connection = 'dis';

    protected $table = 'meta_ig_dm_events';

    protected $fillable = [
        'message_id',
        'thread_id',
        'from_id',
        'page_id',
        'is_from_page',
        'received_at',
        'ad_id',
        'is_first_of_thread',
        'platform',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'is_from_page' => 'boolean',
            'is_first_of_thread' => 'boolean',
            'received_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function scopeInstagram($query)
    {
        return $query->where('platform', 'instagram');
    }

    public function scopeIncoming($query)
    {
        return $query->where('is_from_page', false);
    }

    public function scopeFirstOfThread($query)
    {
        return $query->where('is_first_of_thread', true);
    }

    public function scopeOrganic($query)
    {
        return $query->whereNull('ad_id');
    }
}
