<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\TikTok\TikTokAccount;
use App\Models\TikTok\TikTokAccountSnapshot;
use App\Models\TikTok\TikTokSyncLog;
use App\Models\TikTok\TikTokToken;
use App\Models\TikTok\TikTokVideo;
use App\Services\Tiktok\TiktokApiService;
use App\Services\Tiktok\TiktokSyncService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TiktokMarketingController extends Controller
{
    /**
     * Show the TikTok marketing dashboard.
     */
    public function index()
    {
        $lastSync = TikTokSyncLog::where('status', 'success')
            ->latest()
            ->first();

        $account = TikTokAccount::first();
        $hasToken = TikTokToken::active()->exists();

        return view('tiktok-marketing.index', [
            'lastSync' => $lastSync,
            'account' => $account,
            'hasToken' => $hasToken,
        ]);
    }

    /**
     * KPI cards: followers, likes, videos, engagement rate.
     * Supports ?period=7d|30d|90d for period-based aggregation.
     */
    public function kpis(Request $request): JsonResponse
    {
        $account = TikTokAccount::first();

        if (!$account) {
            return response()->json(['error' => 'No TikTok account synced yet.'], 404);
        }

        // Period-based aggregation using stored deltas
        $periodMap = ['7d' => 7, '30d' => 30, '90d' => 90];
        $periodKey = $request->get('period', '7d');
        $periodDays = $periodMap[$periodKey] ?? 7;

        $periodFrom = Carbon::today()->subDays($periodDays)->toDateString();

        // Sum deltas over the period for change metrics
        $periodChanges = TikTokAccountSnapshot::where('tiktok_account_id', $account->id)
            ->where('date', '>=', $periodFrom)
            ->selectRaw('
                SUM(follower_change) as follower_change,
                SUM(likes_change) as likes_change,
                SUM(total_views_change) as total_views_change,
                SUM(video_count_change) as video_count_change
            ')
            ->first();

        // Video-level aggregates
        $totalVideos = TikTokVideo::where('tiktok_account_id', $account->id)->count();
        $totalViews = (int) TikTokVideo::where('tiktok_account_id', $account->id)->sum('view_count');
        $totalLikes = (int) TikTokVideo::where('tiktok_account_id', $account->id)->sum('like_count');
        $totalComments = (int) TikTokVideo::where('tiktok_account_id', $account->id)->sum('comment_count');
        $totalShares = (int) TikTokVideo::where('tiktok_account_id', $account->id)->sum('share_count');

        $avgEngagement = $totalViews > 0
            ? round((($totalLikes + $totalComments + $totalShares) / $totalViews) * 100, 2)
            : 0;

        return response()->json([
            'period' => $periodKey,
            'followers' => [
                'value' => $account->follower_count,
                'period_change' => (int) ($periodChanges->follower_change ?? 0),
            ],
            'total_likes' => [
                'value' => $account->likes_count,
                'period_change' => (int) ($periodChanges->likes_change ?? 0),
            ],
            'total_videos' => [
                'value' => $totalVideos,
                'period_change' => (int) ($periodChanges->video_count_change ?? 0),
            ],
            'total_views' => [
                'value' => $totalViews,
                'period_change' => (int) ($periodChanges->total_views_change ?? 0),
            ],
            'total_comments' => [
                'value' => $totalComments,
            ],
            'total_shares' => [
                'value' => $totalShares,
            ],
            'avg_engagement_rate' => [
                'value' => $avgEngagement,
            ],
        ]);
    }

    /**
     * Follower growth chart data with daily deltas.
     * Supports ?days=30|60|90 (default 90).
     */
    public function followerChart(Request $request): JsonResponse
    {
        $account = TikTokAccount::first();

        if (!$account) {
            return response()->json([]);
        }

        $days = min((int) $request->get('days', 90), 365);
        $from = Carbon::today()->subDays($days)->toDateString();

        $snapshots = TikTokAccountSnapshot::where('tiktok_account_id', $account->id)
            ->where('date', '>=', $from)
            ->orderBy('date')
            ->get()
            ->map(function ($s) {
                return [
                    'date' => $s->date->format('Y-m-d'),
                    'followers' => $s->follower_count,
                    'likes' => $s->likes_count,
                    'follower_change' => $s->follower_change,
                    'likes_change' => $s->likes_change,
                    'total_views_change' => $s->total_views_change,
                ];
            });

        return response()->json($snapshots);
    }

    /**
     * Top videos by views.
     */
    public function topVideos(Request $request): JsonResponse
    {
        $limit = min((int) $request->get('limit', 20), 100);
        $sortBy = $request->get('sort', 'view_count');

        $allowedSorts = ['view_count', 'like_count', 'comment_count', 'share_count', 'created_at_tiktok'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'view_count';
        }

        $videos = TikTokVideo::orderByDesc($sortBy)
            ->limit($limit)
            ->get()
            ->map(fn($video) => $this->formatVideo($video));

        return response()->json($videos);
    }

    /**
     * All videos table data with sorting.
     */
    public function videos(Request $request): JsonResponse
    {
        $sortBy = $request->get('sort', 'created_at_tiktok');
        $sortDir = $request->get('dir', 'desc');

        $allowedSorts = ['view_count', 'like_count', 'comment_count', 'share_count', 'created_at_tiktok', 'duration'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at_tiktok';
        }

        $videos = TikTokVideo::orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc')
            ->get()
            ->map(fn($video) => $this->formatVideo($video));

        return response()->json($videos);
    }

    /**
     * Trigger a manual sync.
     * Supports ?deltas=true for delta-aware sync.
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            $api = app(TiktokApiService::class);
            $syncService = new TiktokSyncService($api);

            $useDeltas = $request->boolean('deltas', false);
            $results = $useDeltas
                ? $syncService->syncWithDeltas()
                : $syncService->syncAll();

            return response()->json([
                'success' => true,
                'results' => $results,
            ]);
        } catch (Exception $e) {
            Log::error('TikTok manual sync failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format a video model for JSON response.
     */
    private function formatVideo(TikTokVideo $video): array
    {
        return [
            'id' => $video->video_id,
            'title' => $video->title ?: mb_substr($video->video_description ?? '', 0, 80),
            'description' => $video->video_description,
            'cover_image_url' => $video->cover_image_url,
            'share_url' => $video->share_url,
            'duration' => $video->duration,
            'view_count' => $video->view_count,
            'like_count' => $video->like_count,
            'comment_count' => $video->comment_count,
            'share_count' => $video->share_count,
            'engagement_rate' => $video->engagement_rate,
            'total_engagement' => $video->total_engagement,
            'created_at' => $video->created_at_tiktok?->format('Y-m-d H:i'),
        ];
    }
}
