<?php

namespace App\Models\Content;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentComment extends Model
{
    use SoftDeletes;

    protected $table = 'content_comments';

    protected $fillable = [
        'content_post_id',
        'user_id',
        'guest_name',
        'body',
        'parent_id',
        'is_internal',
        'resolved_at',
        'external_id',
        'external_platform',
        'external_author',
        'external_author_avatar',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(ContentPost::class, 'content_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ContentComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ContentComment::class, 'parent_id');
    }
}
