<?php

namespace App\Models\Content;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContentLabel extends Model
{
    protected $table = 'content_labels';

    protected $fillable = [
        'name',
        'color',
    ];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(ContentPost::class, 'content_post_labels');
    }
}
