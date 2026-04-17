<?php

namespace App\Models\Meta;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Stored Meta API tokens (user tokens, page tokens, system tokens).
 *
 * @property int         $id
 * @property string      $name
 * @property string      $token_type
 * @property string      $access_token
 * @property string|null $meta_user_id
 * @property string|null $page_id
 * @property string|null $ig_account_id
 * @property array|null  $scopes
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon|null $last_used_at
 * @property bool        $is_active
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class MetaToken extends Model
{
    // Meta data lives in the DIS database (shared with the monolith).
    protected $connection = 'dis';

    protected $table = 'meta_tokens';

    protected $fillable = [
        'name',
        'token_type',
        'access_token',
        'meta_user_id',
        'page_id',
        'ig_account_id',
        'scopes',
        'expires_at',
        'last_used_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'scopes'       => 'array',
            'expires_at'   => 'datetime',
            'last_used_at' => 'datetime',
            'is_active'    => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function expiresSoon(): bool
    {
        return $this->expires_at && $this->expires_at->isBetween(now(), now()->addDays(7));
    }
}
