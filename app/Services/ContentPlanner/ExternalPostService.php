<?php

namespace App\Services\ContentPlanner;

use App\Models\Meta\MetaPostInsight;
use App\Models\TikTok\TikTokVideo;
use Carbon\Carbon;

class ExternalPostService
{
    /**
     * Shape the mediaItems relation into a lean array that the grid/list views
     * can render. Prefer local paths (survive IG token expiry) over the
     * original Meta CDN URL.
     *
     * Returns array of:
     *   [ 'url' => ..., 'thumbnail' => ..., 'is_video' => bool, 'position' => int ]
     *
     * Falls back to a single synthetic item from `media_url` for posts that
     * were synced before the media-items schema change — keeps the UI working.
     */
    private function truncate(?string $value, int $limit = 60): string
    {
        if ($value === null) return '';
        $plain = strip_tags($value);
        return mb_strwidth($plain, 'UTF-8') <= $limit
            ? $plain
            : rtrim(mb_strimwidth($plain, 0, $limit, '', 'UTF-8')).'...';
    }

    private function buildMediaItemsFromPost(MetaPostInsight $post): array
    {
        $out = [];
        $items = $post->relationLoaded('mediaItems') ? $post->mediaItems : collect();

        foreach ($items as $m) {
            $out[] = [
                'url'       => $m->display_url,
                'thumbnail' => $m->display_thumbnail,
                'is_video'  => $m->isVideo(),
                'position'  => (int) $m->position,
            ];
        }

        // Legacy fallback — existing posts without media rows
        if (empty($out) && $post->media_url) {
            $out[] = [
                'url'       => $post->media_url,
                'thumbnail' => $post->media_url,
                'is_video'  => false,
                'position'  => 0,
            ];
        }

        return $out;
    }


    /**
     * Get externally published posts (FB/IG/TikTok) formatted as FullCalendar events.
     *
     * $max is a safety ceiling to avoid OOM on pages with decades of history —
     * the grid frontend paginates client-side, so serving the full recent set
     * is intentional (was capped at 200 which made thousands of IG posts
     * invisible to the planner grid).
     */
    public function getExternalPostsForCalendar(
        Carbon $from,
        Carbon $to,
        ?array $platforms = null,
        int $max = 10000,
    ): array {
        $events = [];

        $includeFb = !$platforms || in_array('facebook', $platforms);
        $includeIg = !$platforms || in_array('instagram', $platforms);
        $includeTt = !$platforms || in_array('tiktok', $platforms);

        // Meta posts (Facebook + Instagram) — group cross-posted content into single events
        if ($includeFb || $includeIg) {
            $query = MetaPostInsight::whereBetween('created_at_meta', [$from, $to]);

            if ($includeFb && !$includeIg) {
                $query->where('source', 'facebook');
            } elseif ($includeIg && !$includeFb) {
                $query->where('source', 'instagram');
            }

            $metaPosts = $query->with('mediaItems')
                ->orderBy('created_at_meta', 'desc')
                ->limit($max)
                ->get();

            // Group posts with the same message that were published within 24 hours of each other
            $grouped = [];
            foreach ($metaPosts as $post) {
                // Normalize whitespace and use first 100 chars — handles minor FB/IG formatting differences
                $normalized = mb_substr(preg_replace('/\s+/', ' ', trim($post->message ?? '')), 0, 100);
                $key = md5($normalized);
                $matched = false;

                if (isset($grouped[$key])) {
                    foreach ($grouped[$key] as &$group) {
                        $timeDiff = abs($post->created_at_meta->diffInHours($group['date']));
                        if ($timeDiff <= 24) {
                            $group['posts'][] = $post;
                            $matched = true;
                            break;
                        }
                    }
                    unset($group);
                }

                if (!$matched) {
                    $grouped[$key][] = [
                        'date' => $post->created_at_meta,
                        'posts' => [$post],
                    ];
                }
            }

            // Build events from grouped posts
            foreach ($grouped as $groups) {
                foreach ($groups as $group) {
                    $posts = $group['posts'];
                    $primary = $posts[0]; // Use first post for main data
                    $platforms = array_unique(array_map(fn ($p) => $p->source ?? 'facebook', $posts));
                    $ids = array_map(fn ($p) => $p->id, $posts);
                    sort($platforms);

                    // Use the first platform for colors if single, otherwise use a neutral style
                    $isMulti = count($platforms) > 1;
                    $displayPlatform = $isMulti ? 'multi' : $platforms[0];

                    // Sum metrics across platforms
                    $totalReach = array_sum(array_map(fn ($p) => $p->reach ?? 0, $posts));
                    $totalImpressions = array_sum(array_map(fn ($p) => $p->impressions ?? 0, $posts));
                    $totalLikes = array_sum(array_map(fn ($p) => $p->likes ?? 0, $posts));
                    $totalComments = array_sum(array_map(fn ($p) => $p->comments ?? 0, $posts));
                    $totalShares = array_sum(array_map(fn ($p) => $p->shares ?? 0, $posts));
                    $totalSaves = array_sum(array_map(fn ($p) => $p->saves ?? 0, $posts));
                    $engagementRate = $totalReach > 0
                        ? round(($totalLikes + $totalComments + $totalShares + $totalSaves) / $totalReach * 100, 2)
                        : null;

                    // Collect permalinks per platform
                    $permalinks = [];
                    foreach ($posts as $p) {
                        $permalinks[$p->source ?? 'facebook'] = $p->permalink_url;
                    }

                    // Build full media array from mediaItems (carousel-aware).
                    // Falls back to media_url scalar if no mediaItems synced yet
                    // (old posts pre-dating the schema change).
                    $mediaItems = $this->buildMediaItemsFromPost($primary);
                    $firstMedia = $mediaItems[0] ?? null;
                    $hasVideo   = !empty(array_filter($mediaItems, fn ($m) => $m['is_video']));

                    $events[] = [
                        'id' => 'ext_meta_' . implode('_', $ids),
                        'title' => $this->truncate($primary->message, 60),
                        'start' => $primary->created_at_meta->toIso8601String(),
                        'allDay' => false,
                        'backgroundColor' => $isMulti ? '#F0F0FF' : $this->platformBgColor($platforms[0]),
                        'borderColor' => $isMulti ? '#6366F1' : $this->platformColor($platforms[0]),
                        'textColor' => '#374151',
                        'editable' => false,
                        'extendedProps' => [
                            'is_external' => true,
                            'status' => 'published_external',
                            'status_label' => 'Published',
                            'status_color' => $isMulti ? '#6366F1' : $this->platformColor($platforms[0]),
                            'status_bg_color' => $isMulti ? '#F0F0FF' : $this->platformBgColor($platforms[0]),
                            'platform' => $isMulti ? 'multi' : $platforms[0],
                            'platform_icons' => array_values($platforms),
                            'thumbnail' => $firstMedia['thumbnail'] ?? $primary->media_url,
                            'first_media_url' => $firstMedia['url'] ?? $primary->media_url,
                            'is_video' => $firstMedia['is_video'] ?? false,
                            'has_video' => $hasVideo,
                            'media_items' => $mediaItems,
                            'content' => $primary->message,
                            'permalink' => $primary->permalink_url,
                            'permalinks' => $permalinks,
                            'labels' => [],
                            'user_name' => null,
                            'media_count' => count($mediaItems) ?: ($primary->media_url ? 1 : 0),
                            'has_media' => !empty($mediaItems) || (bool) $primary->media_url,
                            'metrics' => [
                                'reach' => $totalReach,
                                'impressions' => $totalImpressions,
                                'likes' => $totalLikes,
                                'comments' => $totalComments,
                                'shares' => $totalShares,
                                'saves' => $totalSaves,
                                'engagement_rate' => $engagementRate,
                            ],
                        ],
                    ];
                }
            }
        }

        // TikTok videos
        if ($includeTt) {
            $tiktokVideos = TiktokVideo::whereBetween('created_at_tiktok', [$from, $to])
                ->orderBy('created_at_tiktok', 'desc')
                ->limit($max)
                ->get();

            foreach ($tiktokVideos as $video) {
                $events[] = [
                    'id' => "ext_tiktok_{$video->id}",
                    'title' => $this->truncate($video->title ?: $video->video_description, 60),
                    'start' => $video->created_at_tiktok->toIso8601String(),
                    'allDay' => false,
                    'backgroundColor' => '#F5F5F5',
                    'borderColor' => '#010101',
                    'textColor' => '#374151',
                    'editable' => false,
                    'extendedProps' => [
                        'is_external' => true,
                        'status' => 'published_external',
                        'status_label' => 'Published',
                        'status_color' => '#010101',
                        'status_bg_color' => '#F5F5F5',
                        'platform' => 'tiktok',
                        'platform_icons' => ['tiktok'],
                        'thumbnail' => $video->cover_image_url,
                        'content' => $video->title ?: $video->video_description,
                        'permalink' => $video->share_url,
                        'labels' => [],
                        'user_name' => null,
                        'media_count' => 1,
                        'has_media' => true,
                        'metrics' => [
                            'view_count' => $video->view_count ?? 0,
                            'like_count' => $video->like_count ?? 0,
                            'comment_count' => $video->comment_count ?? 0,
                            'share_count' => $video->share_count ?? 0,
                            'engagement_rate' => $video->engagement_rate,
                        ],
                    ],
                ];
            }
        }

        return $events;
    }

    /**
     * Get external posts for the feed view (chronological, merged with planned posts).
     */
    public function getExternalPostsForFeed(
        Carbon $from,
        Carbon $to,
        ?array $platforms = null,
        int $limit = 50,
    ): array {
        $posts = [];

        $includeFb = !$platforms || in_array('facebook', $platforms);
        $includeIg = !$platforms || in_array('instagram', $platforms);
        $includeTt = !$platforms || in_array('tiktok', $platforms);

        if ($includeFb || $includeIg) {
            $query = MetaPostInsight::whereBetween('created_at_meta', [$from, $to]);
            if ($includeFb && !$includeIg) $query->where('source', 'facebook');
            elseif ($includeIg && !$includeFb) $query->where('source', 'instagram');

            $metaPosts = $query->with('mediaItems')
                ->orderBy('created_at_meta', 'desc')
                ->limit($limit)
                ->get();

            // Group cross-posted content
            $grouped = [];
            foreach ($metaPosts as $post) {
                // Normalize whitespace and use first 100 chars — handles minor FB/IG formatting differences
                $normalized = mb_substr(preg_replace('/\s+/', ' ', trim($post->message ?? '')), 0, 100);
                $key = md5($normalized);
                $matched = false;

                if (isset($grouped[$key])) {
                    foreach ($grouped[$key] as &$group) {
                        if (abs($post->created_at_meta->diffInHours($group['date'])) <= 24) {
                            $group['posts'][] = $post;
                            $matched = true;
                            break;
                        }
                    }
                    unset($group);
                }

                if (!$matched) {
                    $grouped[$key][] = [
                        'date' => $post->created_at_meta,
                        'posts' => [$post],
                    ];
                }
            }

            foreach ($grouped as $groups) {
                foreach ($groups as $group) {
                    $primary = $group['posts'][0];
                    $platforms = array_unique(array_map(fn ($p) => $p->source ?? 'facebook', $group['posts']));
                    $ids = array_map(fn ($p) => $p->id, $group['posts']);

                    $mediaItems = $this->buildMediaItemsFromPost($primary);
                    $firstMedia = $mediaItems[0] ?? null;

                    $posts[] = [
                        'id' => 'ext_meta_' . implode('_', $ids),
                        'type' => 'external',
                        'platform' => count($platforms) > 1 ? 'multi' : $platforms[0],
                        'platform_icons' => array_values($platforms),
                        'content' => $primary->message,
                        'thumbnail' => $firstMedia['thumbnail'] ?? $primary->media_url,
                        'first_media_url' => $firstMedia['url'] ?? $primary->media_url,
                        'is_video' => $firstMedia['is_video'] ?? false,
                        'media_items' => $mediaItems,
                        'media_count' => count($mediaItems) ?: ($primary->media_url ? 1 : 0),
                        'has_media' => !empty($mediaItems) || (bool) $primary->media_url,
                        'permalink' => $primary->permalink_url,
                        'published_at' => $primary->created_at_meta->toIso8601String(),
                        'sort_date' => $primary->created_at_meta->toIso8601String(),
                        'metrics' => [
                            'reach' => array_sum(array_map(fn ($p) => $p->reach ?? 0, $group['posts'])),
                            'likes' => array_sum(array_map(fn ($p) => $p->likes ?? 0, $group['posts'])),
                            'comments' => array_sum(array_map(fn ($p) => $p->comments ?? 0, $group['posts'])),
                            'shares' => array_sum(array_map(fn ($p) => $p->shares ?? 0, $group['posts'])),
                            'engagement_rate' => $primary->engagement_rate,
                        ],
                    ];
                }
            }
        }

        if ($includeTt) {
            foreach (TiktokVideo::whereBetween('created_at_tiktok', [$from, $to])->orderBy('created_at_tiktok', 'desc')->limit($limit)->get() as $video) {
                $posts[] = [
                    'id' => "ext_tiktok_{$video->id}",
                    'type' => 'external',
                    'platform' => 'tiktok',
                    'content' => $video->title ?: $video->video_description,
                    'thumbnail' => $video->cover_image_url,
                    'permalink' => $video->share_url,
                    'published_at' => $video->created_at_tiktok->toIso8601String(),
                    'sort_date' => $video->created_at_tiktok->toIso8601String(),
                    'metrics' => [
                        'view_count' => $video->view_count ?? 0,
                        'like_count' => $video->like_count ?? 0,
                        'comment_count' => $video->comment_count ?? 0,
                        'share_count' => $video->share_count ?? 0,
                        'engagement_rate' => $video->engagement_rate,
                    ],
                ];
            }
        }

        return $posts;
    }

    private function platformColor(string $platform): string
    {
        return match ($platform) {
            'facebook' => '#1877F2',
            'instagram' => '#E4405F',
            'tiktok' => '#010101',
            default => '#6B7280',
        };
    }

    private function platformBgColor(string $platform): string
    {
        return match ($platform) {
            'facebook' => '#EBF4FF',
            'instagram' => '#FDF2F4',
            'tiktok' => '#F5F5F5',
            default => '#F3F4F6',
        };
    }
}
