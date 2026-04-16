<?php

namespace App\Models\TikTok;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Stored TikTok API tokens (organic + business).
 *
 * @property int         $id
 * @property string      $name
 * @property string      $token_type
 * @property string|null $open_id
 * @property string|null $union_id
 * @property string|null $advertiser_id
 * @property string      $access_token
 * @property string|null $refresh_token
 * @property array|null  $scopes
 * @property \Carbon\Carbon|null $access_token_expires_at
 * @property \Carbon\Carbon|null $refresh_token_expires_at
 * @property \Carbon\Carbon|null $last_used_at
 * @property bool        $is_active
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class TikTokToken extends Model
{
    protected $table = 'tiktok_tokens';

    protected $fillable = [
        'name',
        'token_type',
        'open_id',
        'union_id',
        'advertiser_id',
        'access_token',
        'refresh_token',
        'scopes',
        'access_token_expires_at',
        'refresh_token_expires_at',
        'last_used_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'scopes'                   => 'array',
            'access_token_expires_at'  => 'datetime',
            'refresh_token_expires_at' => 'datetime',
            'last_used_at'             => 'datetime',
            'is_active'                => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isAccessTokenExpired(): bool
    {
        return $this->access_token_expires_at && $this->access_token_expires_at->isPast();
    }

    public function isRefreshTokenExpired(): bool
    {
        return $this->refresh_token_expires_at && $this->refresh_token_expires_at->isPast();
    }
}
