<?php

namespace App\Models\Content;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentApprovalStep extends Model
{
    protected $table = 'content_approval_steps';

    protected $fillable = [
        'post_id',
        'step_order',
        'role',
        'assigned_to',
        'status',
        'acted_by',
        'acted_at',
        'feedback',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(ContentPost::class, 'post_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
