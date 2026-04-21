<?php

namespace App\Models\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user Canva OAuth token.
 *
 * Tokens are encrypted at rest by the `encrypted` cast — Laravel runs them
 * through Crypt using APP_KEY. If APP_KEY is rotated, the tokens become
 * unreadable and the user must re-authenticate (no auto-migration path).
 *
 * @property int                     $id
 * @property int                     $user_id
 * @property string|null             $canva_user_id
 * @property string|null             $canva_display_name
 * @property string                  $access_token
 * @property string                  $refresh_token
 * @property array|null              $scopes
 * @property \Carbon\Carbon|null     $expires_at
 * @property \Carbon\Carbon|null     $last_used_at
 * @property \Carbon\Carbon|null     $connected_at
 * @property bool                    $is_active
 * @property \Carbon\Carbon|null     $created_at
 * @property \Carbon\Carbon|null     $updated_at
 */
class CanvaConnection extends Model
{
    protected $table = 'marketing_canva_connections';

    protected $fillable = [
        'user_id',
        'canva_user_id',
        'canva_display_name',
        'access_token',
        'refresh_token',
        'scopes',
        'expires_at',
        'last_used_at',
        'connected_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'access_token'  => 'encrypted',
            'refresh_token' => 'encrypted',
            'scopes'        => 'array',
            'expires_at'    => 'datetime',
            'last_used_at'  => 'datetime',
            'connected_at'  => 'datetime',
            'is_active'     => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function expiresSoon(int $minutes = 5): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->isBetween(now(), now()->addMinutes($minutes));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
