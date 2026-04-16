<?php

namespace App\Models\Content;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentShareLink extends Model
{
    protected $table = 'content_share_links';

    protected $fillable = [
        'token',
        'shareable_type',
        'shareable_id',
        'created_by',
        'permission',
        'password_hash',
        'expires_at',
        'view_count',
        'last_viewed_at',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
