<?php

namespace App\Models\Meta;

use Illuminate\Database\Eloquent\Model;

/**
 * Daily messaging statistics per platform (Messenger, Instagram DM, WhatsApp).
 *
 * @property int         $id
 * @property \Carbon\Carbon $date
 * @property string      $platform
 * @property int|null    $new_conversations
 * @property int|null    $total_messages_received
 * @property int|null    $total_messages_sent
 * @property \Carbon\Carbon|null $synced_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class MetaMessagingStat extends Model
{
    protected $table = 'meta_messaging_stats';

    protected $fillable = [
        'date',
        'platform',
        'new_conversations',
        'total_messages_received',
        'total_messages_sent',
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
