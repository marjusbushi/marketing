<?php

namespace App\Services\Departments;

use App\Models\Meta\MetaAdsInsight;
use App\Models\Meta\MetaCampaign;
use App\Models\Meta\MetaPostInsight;
use App\Models\TikTok\TikTokVideo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarketingSuggestionsService
{
    /**
     * Generate actionable task suggestions from current marketing data.
     *
     * @return array<int, array{type: string, severity: string, title: string, description: string, reference_type: string|null, reference_id: int|null}>
     */
    public function getSuggestions(): array
    {
        $suggestions = [];

        try {
            $suggestions = array_merge(
                $suggestions,
                $this->campaignEndingSoon(),
                $this->highEngagementPosts(),
                $this->recentTiktokPerformers(),
            );
        } catch (Throwable $e) {
            Log::warning('MarketingSuggestionsService: Error generating suggestions', [
                'error' => $e->getMessage(),
            ]);
        }

        return array_slice($suggestions, 0, 10);
    }

    /**
     * Campaigns ending within 3 days that need follow-up planning.
     */
    private function campaignEndingSoon(): array
    {
        $suggestions = [];

        $campaigns = MetaCampaign::query()
            ->where('status', 'ACTIVE')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays(3)])
            ->limit(5)
            ->get();

        foreach ($campaigns as $campaign) {
            $daysLeft = (int) now()->diffInDays($campaign->end_date, false);
            $suggestions[] = [
                'type'           => 'campaign_ending',
                'severity'       => 'warning',
                'title'          => "Plan follow-up for \"{$campaign->name}\"",
                'description'    => "Campaign ends in {$daysLeft} day(s). Consider next steps or renewal.",
                'reference_type' => 'meta_campaign',
                'reference_id'   => $campaign->id,
                'category'       => 'campaign_management',
            ];
        }

        return $suggestions;
    }

    /**
     * Recent high-engagement posts worth replicating.
     */
    private function highEngagementPosts(): array
    {
        $suggestions = [];

        $topPosts = MetaPostInsight::query()
            ->where('created_at_meta', '>=', now()->subDays(14))
            ->whereNotNull('reach')
            ->where('reach', '>', 0)
            ->orderByRaw('(likes + comments + shares + saves) DESC')
            ->limit(3)
            ->get();

        foreach ($topPosts as $post) {
            $engagement = $post->likes + $post->comments + $post->shares + $post->saves;
            if ($engagement < 50) {
                continue;
            }

            $preview = mb_substr($post->message ?? 'Post', 0, 60);
            $suggestions[] = [
                'type'           => 'high_engagement',
                'severity'       => 'success',
                'title'          => "Create similar content: \"{$preview}\"",
                'description'    => "This {$post->source} {$post->post_type} got {$engagement} engagements with {$post->engagement_rate}% rate. Replicate this approach.",
                'reference_type' => 'meta_post',
                'reference_id'   => $post->id,
                'category'       => 'content_creation',
            ];
        }

        return $suggestions;
    }

    /**
     * Top-performing recent TikTok videos.
     */
    private function recentTiktokPerformers(): array
    {
        $suggestions = [];

        $videos = TikTokVideo::query()
            ->where('created_at_tiktok', '>=', now()->subDays(14))
            ->where('view_count', '>', 1000)
            ->orderBy('view_count', 'desc')
            ->limit(2)
            ->get();

        foreach ($videos as $video) {
            $preview = mb_substr($video->title ?: ($video->video_description ?? 'Video'), 0, 60);
            $suggestions[] = [
                'type'           => 'tiktok_trending',
                'severity'       => 'info',
                'title'          => "Leverage trending TikTok: \"{$preview}\"",
                'description'    => "{$video->view_count} views, {$video->total_engagement} engagements. Consider boosting or creating follow-up content.",
                'reference_type' => 'tiktok_video',
                'reference_id'   => $video->id,
                'category'       => 'social_media',
            ];
        }

        return $suggestions;
    }
}
