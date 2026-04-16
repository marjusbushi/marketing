<?php

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPostPlatform extends Model
{
    protected $table = 'content_post_platforms';

    protected $fillable = [
        'content_post_id',
        'platform',
        'platform_content',
        'platform_post_id',
        'published_at',
        'status',
        'error_message',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(ContentPost::class, 'content_post_id');
    }
}
