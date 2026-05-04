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
    // Meta data lives in the DIS database (shared with the monolith).
    protected $connection = 'dis';

    protected $table = 'meta_messaging_stats';

    // Tabela ka vetem `synced_at`, jo Eloquent's created_at/updated_at.
    public $timestamps = false;

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

    public function scopeMessenger($query)
    {
        return $query->where('platform', 'messenger');
    }

    public function scopeInstagram($query)
    {
        return $query->where('platform', 'instagram');
    }
}
