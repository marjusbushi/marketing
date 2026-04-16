<?php

namespace App\Services\Tiktok;

use App\Models\TikTok\TikTokAccount;
use App\Models\TikTok\TikTokAccountSnapshot;
use App\Models\TikTok\TikTokSyncLog;
use App\Models\TikTok\TikTokVideo;
use App\Models\TikTok\TikTokVideoSnapshot;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TiktokSyncService
{
    private TiktokApiService $api;

    public function __construct(TiktokApiService $api)
    {
        $this->api = $api;
    }

    /**
     * Run full sync: account info + all videos (no deltas).
     */
    public function syncAll(): array
    {
        $results = [];

        try {
            $results['account'] = $this->syncAccount();
        } catch (Exception $e) {
            Log::error('TikTok account sync failed: ' . $e->getMessage());
            $results['account'] = ['status' => 'failed', 'error' => $e->getMessage()];
        }

        sleep($this->getPauseBetweenBatches());

        try {
            $results['videos'] = $this->syncVideos();
        } catch (Exception $e) {
            Log::error('TikTok videos sync failed: ' . $e->getMessage());
            $results['videos'] = ['status' => 'failed', 'error' => $e->getMessage()];
        }

        return $results;
    }

    /**
     * Run full sync WITH delta computation.
     * Uses a cache lock to prevent concurrent runs from corrupting deltas.
     */
    public function syncWithDeltas(): array
    {
        $lock = Cache::lock('tiktok:sync-deltas', 600); // 10 minute lock

        if (!$lock->get()) {
            Log::warning('TikTok delta sync skipped — another sync is already running.');
            return [
                'status' => 'skipped',
                'reason' => 'Another sync is already running.',
            ];
        }

        try {
            $results = [];

            try {
                $results['account'] = $this->syncAccountWithDeltas();
            } catch (Exception $e) {
                Log::error('TikTok account delta sync failed: ' . $e->getMessage());
                $results['account'] = ['status' => 'failed', 'error' => $e->getMessage()];
            }

            sleep($this->getPauseBetweenBatches());

            try {
                $results['videos'] = $this->syncVideosWithDeltas();
            } catch (Exception $e) {
                Log::error('TikTok video delta sync failed: ' . $e->getMessage());
                $results['videos'] = ['status' => 'failed', 'error' => $e->getMessage()];
            }

            return $results;
        } finally {
            $lock->release();
        }
    }

    /**
     * Sync account info and take a daily snapshot.
     */
    public function syncAccount(): array
    {
        $log = TikTokSyncLog::start('full', 'account');

        try {
            $this->api->resetApiCallsCount();

            $response = $this->api->getUserInfo();
            $userData = $response['data']['user'] ?? null;

            if (!$userData) {
                throw new Exception('No user data returned from TikTok API.');
            }

            $account = $this->upsertAccount($userData);

            // Take daily snapshot (no deltas)
            TikTokAccountSnapshot::updateOrCreate(
                [
                    'tiktok_account_id' => $account->id,
                    'date' => Carbon::today()->toDateString(),
                ],
                [
                    'follower_count' => $userData['follower_count'] ?? 0,
                    'following_count' => $userData['following_count'] ?? 0,
                    'likes_count' => $userData['likes_count'] ?? 0,
                    'video_count' => $userData['video_count'] ?? 0,
                ]
            );

            $log->markSuccess(1, $this->api->getApiCallsCount());

            return [
                'status' => 'success',
                'account' => $account->display_name,
                'followers' => $account->follower_count,
            ];
        } catch (Exception $e) {
            $log->markFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync account info with delta computation.
     */
    public function syncAccountWithDeltas(): array
    {
        $log = TikTokSyncLog::start('daily', 'account_deltas');

        try {
            $this->api->resetApiCallsCount();

            $response = $this->api->getUserInfo();
            $userData = $response['data']['user'] ?? null;

            if (!$userData) {
                throw new Exception('No user data returned from TikTok API.');
            }

            $account = $this->upsertAccount($userData);
            $today = Carbon::today()->toDateString();

            // Get previous snapshot for delta computation
            $previousSnapshot = TikTokAccountSnapshot::where('tiktok_account_id', $account->id)
                ->where('date', '<', $today)
                ->orderByDesc('date')
                ->first();

            $currentFollowers = $userData['follower_count'] ?? 0;
            $currentFollowing = $userData['following_count'] ?? 0;
            $currentLikes = $userData['likes_count'] ?? 0;
            $currentVideoCount = $userData['video_count'] ?? 0;

            // Compute deltas (null if no previous snapshot = first sync)
            $followerChange = $previousSnapshot ? $currentFollowers - $previousSnapshot->follower_count : null;
            $followingChange = $previousSnapshot ? $currentFollowing - $previousSnapshot->following_count : null;
            $likesChange = $previousSnapshot ? $currentLikes - $previousSnapshot->likes_count : null;
            $videoCountChange = $previousSnapshot ? $currentVideoCount - $previousSnapshot->video_count : null;

            // Upsert today's snapshot WITH deltas
            $snapshot = TikTokAccountSnapshot::updateOrCreate(
                [
                    'tiktok_account_id' => $account->id,
                    'date' => $today,
                ],
                [
                    'follower_count' => $currentFollowers,
                    'following_count' => $currentFollowing,
                    'likes_count' => $currentLikes,
                    'video_count' => $currentVideoCount,
                    'follower_change' => $followerChange,
                    'following_change' => $followingChange,
                    'likes_change' => $likesChange,
                    'video_count_change' => $videoCountChange,
                ]
            );

            $log->markSuccess(1, $this->api->getApiCallsCount());

            return [
                'status' => 'success',
                'account' => $account->display_name,
                'followers' => $currentFollowers,
                'follower_change' => $followerChange,
                'likes_change' => $likesChange,
            ];
        } catch (Exception $e) {
            $log->markFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync all videos with their metrics (no deltas).
     */
    public function syncVideos(): array
    {
        $log = TikTokSyncLog::start('full', 'videos');

        try {
            $this->api->resetApiCallsCount();
            $account = TikTokAccount::first();

            if (!$account) {
                throw new Exception('No TikTok account found. Sync account first.');
            }

            $result = $this->fetchAndSyncVideos($account);

            if ($result['failed'] > 0 && $result['synced'] > 0) {
                $log->markPartial($result['synced'], $result['failed'], "{$result['failed']} videos failed to sync");
            } else {
                $log->markSuccess($result['synced'], $this->api->getApiCallsCount());
            }

            return array_merge(['status' => 'success'], $result);
        } catch (Exception $e) {
            $log->markFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync all videos WITH delta computation (daily snapshots per video).
     */
    public function syncVideosWithDeltas(): array
    {
        $log = TikTokSyncLog::start('daily', 'video_deltas');

        try {
            $this->api->resetApiCallsCount();
            $account = TikTokAccount::first();

            if (!$account) {
                throw new Exception('No TikTok account found. Sync account first.');
            }

            $today = Carbon::today()->toDateString();
            $result = $this->fetchAndSyncVideos($account);

            // Now create video snapshots with deltas
            $totalViewsChange = 0;
            $snapshotCount = 0;

            $videos = TikTokVideo::where('tiktok_account_id', $account->id)->get();

            foreach ($videos as $video) {
                try {
                    // Get previous video snapshot
                    $previousVideoSnapshot = TikTokVideoSnapshot::where('tiktok_video_id', $video->id)
                        ->where('date', '<', $today)
                        ->orderByDesc('date')
                        ->first();

                    // Compute deltas — for new videos (no previous), count their FULL current values
                    // as "change" since they didn't exist before
                    $viewChange = $previousVideoSnapshot
                        ? $video->view_count - $previousVideoSnapshot->view_count
                        : $video->view_count; // New video: all views are "new"
                    $likeChange = $previousVideoSnapshot
                        ? $video->like_count - $previousVideoSnapshot->like_count
                        : $video->like_count;
                    $commentChange = $previousVideoSnapshot
                        ? $video->comment_count - $previousVideoSnapshot->comment_count
                        : $video->comment_count;
                    $shareChange = $previousVideoSnapshot
                        ? $video->share_count - $previousVideoSnapshot->share_count
                        : $video->share_count;

                    TikTokVideoSnapshot::updateOrCreate(
                        [
                            'tiktok_video_id' => $video->id,
                            'date' => $today,
                        ],
                        [
                            'view_count' => $video->view_count,
                            'like_count' => $video->like_count,
                            'comment_count' => $video->comment_count,
                            'share_count' => $video->share_count,
                            'view_change' => $viewChange,
                            'like_change' => $likeChange,
                            'comment_change' => $commentChange,
                            'share_change' => $shareChange,
                        ]
                    );

                    $totalViewsChange += $viewChange;
                    $snapshotCount++;
                } catch (Exception $e) {
                    Log::warning('Failed to create video snapshot', [
                        'video_id' => $video->video_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Write aggregated total_views_change back to today's account snapshot
            $accountSnapshot = TikTokAccountSnapshot::where('tiktok_account_id', $account->id)
                ->where('date', $today)
                ->first();

            if ($accountSnapshot) {
                $accountSnapshot->update(['total_views_change' => $totalViewsChange]);
            }

            if ($result['failed'] > 0 && $result['synced'] > 0) {
                $log->markPartial($snapshotCount, $result['failed'], "{$result['failed']} videos failed");
            } else {
                $log->markSuccess($snapshotCount, $this->api->getApiCallsCount());
            }

            return [
                'status' => 'success',
                'synced' => $result['synced'],
                'failed' => $result['failed'],
                'snapshots_created' => $snapshotCount,
                'total_views_change' => $totalViewsChange,
            ];
        } catch (Exception $e) {
            $log->markFailed($e->getMessage());
            throw $e;
        }
    }

    // ─── Private Helpers ──────────────────────────────────

    /**
     * Upsert a TikTokAccount from API user data.
     */
    private function upsertAccount(array $userData): TikTokAccount
    {
        return TikTokAccount::updateOrCreate(
            ['open_id' => $userData['open_id']],
            [
                'union_id' => $userData['union_id'] ?? null,
                'display_name' => $userData['display_name'] ?? null,
                'username' => $userData['username'] ?? null,
                'avatar_url' => $userData['avatar_url'] ?? null,
                'bio_description' => $userData['bio_description'] ?? null,
                'is_verified' => $userData['is_verified'] ?? false,
                'profile_deep_link' => $userData['profile_deep_link'] ?? null,
                'follower_count' => $userData['follower_count'] ?? 0,
                'following_count' => $userData['following_count'] ?? 0,
                'likes_count' => $userData['likes_count'] ?? 0,
                'video_count' => $userData['video_count'] ?? 0,
            ]
        );
    }

    /**
     * Fetch all videos from TikTok API and upsert into DB.
     * Shared between syncVideos() and syncVideosWithDeltas().
     */
    private function fetchAndSyncVideos(TikTokAccount $account): array
    {
        $cursor = null;
        $hasMore = true;
        $totalSynced = 0;
        $totalFailed = 0;
        $maxVideos = $this->getMaxVideosToSync();
        $pauseBetween = $this->getPauseBetweenBatches();

        while ($hasMore && $totalSynced < $maxVideos) {
            $response = $this->api->getVideoList($cursor);

            $videos = $response['data']['videos'] ?? [];
            $hasMore = $response['data']['has_more'] ?? false;
            $cursor = $response['data']['cursor'] ?? null;

            if (empty($videos)) {
                break;
            }

            foreach ($videos as $videoData) {
                try {
                    TikTokVideo::updateOrCreate(
                        ['video_id' => $videoData['id']],
                        [
                            'tiktok_account_id' => $account->id,
                            'title' => $videoData['title'] ?? null,
                            'video_description' => $videoData['video_description'] ?? null,
                            'cover_image_url' => $videoData['cover_image_url'] ?? null,
                            'share_url' => $videoData['share_url'] ?? null,
                            'embed_link' => $videoData['embed_link'] ?? null,
                            'duration' => $videoData['duration'] ?? 0,
                            'width' => $videoData['width'] ?? null,
                            'height' => $videoData['height'] ?? null,
                            'view_count' => $videoData['view_count'] ?? 0,
                            'like_count' => $videoData['like_count'] ?? 0,
                            'comment_count' => $videoData['comment_count'] ?? 0,
                            'share_count' => $videoData['share_count'] ?? 0,
                            'created_at_tiktok' => isset($videoData['create_time'])
                                ? Carbon::createFromTimestamp($videoData['create_time'])
                                : null,
                        ]
                    );
                    $totalSynced++;
                } catch (Exception $e) {
                    Log::warning('Failed to sync TikTok video', [
                        'video_id' => $videoData['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    $totalFailed++;
                }
            }

            if ($hasMore) {
                sleep($pauseBetween);
            }
        }

        return ['synced' => $totalSynced, 'failed' => $totalFailed];
    }

    private function getMaxVideosToSync(): int
    {
        return config('tiktok.max_videos_to_sync', 200);
    }

    private function getPauseBetweenBatches(): int
    {
        return config('tiktok.pause_between_batches', 3);
    }
}
