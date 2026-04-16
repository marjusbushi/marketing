<?php

namespace App\Models\Content;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPostVersion extends Model
{
    protected $table = 'content_post_versions';

    protected $fillable = [
        'post_id',
        'version_number',
        'snapshot',
        'change_summary',
        'created_by',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(ContentPost::class, 'post_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
