<?php

namespace App\Models;

use App\Models\Concerns\HasMarketingRole;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User model pointing to the shared DIS users table.
 *
 * Marketing does NOT create its own users — all identity
 * lives in the DIS database. This model reads from `dis.users`.
 *
 * @property int         $id
 * @property int|null    $role_id
 * @property string      $first_name
 * @property string      $last_name
 * @property string      $full_name
 * @property string      $email
 * @property string|null $mobile
 * @property string|null $avatar_path
 * @property string      $locale
 * @property string      $password
 * @property string|null $remember_token
 * @property \Carbon\Carbon|null $email_verified_at
 * @property \Carbon\Carbon|null $banned_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class User extends Authenticatable
{
    use HasMarketingRole;
    use Notifiable;
    use SoftDeletes;

    /**
     * Use the DIS database connection — shared identity store.
     */
    protected $connection = 'dis';

    /**
     * The table in the DIS database.
     */
    protected $table = 'users';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'banned_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Full name accessor (mirrors DIS User).
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Initials for avatar display.
     */
    public function getInitialsAttribute(): string
    {
        return mb_strtoupper(
            mb_substr($this->first_name ?? '', 0, 1)
            . mb_substr($this->last_name ?? '', 0, 1)
        );
    }
}
