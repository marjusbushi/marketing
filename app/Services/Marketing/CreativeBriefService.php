<?php

namespace App\Services\Marketing;

use App\Models\DailyBasketPost;
use App\Models\Marketing\CreativeBrief;
use App\Models\Marketing\Template;
use Illuminate\Support\Facades\DB;

/**
 * Creative Brief lifecycle — create, persist editor state, duplicate, link.
 *
 * Duplication preserves all fields except ids, timestamps, and the
 * daily_basket_post link — a duplicate is a standalone draft until the
 * caller explicitly attaches it.
 */
class CreativeBriefService
{
    public function createForPost(
        DailyBasketPost $post,
        string $postType,
        ?Template $template = null,
        ?int $userId = null,
    ): CreativeBrief {
        return DB::transaction(function () use ($post, $postType, $template, $userId) {
            $brief = CreativeBrief::query()->create([
                'daily_basket_post_id' => $post->id,
                'template_id'          => $template?->id,
                'post_type'            => $postType,
                'source'               => 'manual',
                'created_by'           => $userId,
            ]);

            if ($post->creative_brief_id === null) {
                $post->creative_brief_id = $brief->id;
                $post->save();
            }

            return $brief;
        });
    }

    public function loadForPost(DailyBasketPost $post): ?CreativeBrief
    {
        if ($post->creative_brief_id === null) {
            return null;
        }

        return CreativeBrief::query()->find($post->creative_brief_id);
    }

    public function updateState(CreativeBrief $brief, array $state): CreativeBrief
    {
        $brief->state = $state;
        $brief->save();

        return $brief;
    }

    public function updateFields(CreativeBrief $brief, array $attributes): CreativeBrief
    {
        $brief->fill($attributes)->save();

        return $brief->refresh();
    }

    public function duplicate(CreativeBrief $brief): CreativeBrief
    {
        $attributes = $brief->only([
            'template_id',
            'post_type',
            'aspect',
            'duration_sec',
            'caption_sq',
            'caption_en',
            'hashtags',
            'music_id',
            'script',
            'media_slots',
            'source',
            'ai_prompt_version',
            'state',
        ]);

        return CreativeBrief::query()->create($attributes);
    }
}
