<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Middleware\MetaMarketingCache;
use App\Jobs\MetaForceRefreshJob;
use App\Models\Meta\MetaSyncLog;
use App\Services\Meta\MetaDataResolverService;
use App\Services\Meta\MetaMarketingV2ChannelService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaMarketingV2ChannelsController extends Controller
{
    public function adsReport()
    {
        $lastSync = MetaSyncLog::where('status', 'success')
            ->where('data_type', 'ads')
            ->latest()
            ->first();

        return view('meta-marketing.ads', [
            'lastSync' => $lastSync,
            'apiVersionV24' => config('meta.api_version', 'v24.0'),
        ]);
    }

    public function instagramReport()
    {
        $lastSync = MetaSyncLog::where('status', 'success')
            ->where('data_type', 'ig')
            ->latest()
            ->first();

        return view('meta-marketing.instagram', [
            'lastSync' => $lastSync,
            'apiVersionV24' => config('meta.api_version', 'v24.0'),
        ]);
    }

    public function facebookReport()
    {
        abort_unless(config('meta.features.facebook_module', false), 404);

        $lastSync = MetaSyncLog::where('status', 'success')
            ->whereIn('data_type', ['page', 'posts', 'messaging'])
            ->latest()
            ->first();

        return view('meta-marketing.facebook', [
            'lastSync' => $lastSync,
            'apiVersionV24' => config('meta.api_version', 'v24.0'),
        ]);
    }

    public function syncData(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        if (config('meta.features.db_first_mode', false)) {
            // Prevent duplicate dispatch while a job is already running
            $current = Cache::get(MetaForceRefreshJob::STATUS_KEY, []);
            if (($current['status'] ?? null) === 'syncing') {
                return response()->json([
                    'success' => true,
                    'queued' => true,
                    'already_running' => true,
                    'message' => 'Rifresko tashmë po ekzekutohet...',
                    'from' => $current['from'] ?? $from,
                    'to' => $current['to'] ?? $to,
                ]);
            }

            $channel = $request->input('channel'); // null = all, 'instagram', 'facebook', 'ads'
            MetaForceRefreshJob::dispatch($from, $to, $channel);

            return response()->json([
                'success' => true,
                'queued' => true,
                'message' => 'Rifresko po ekzekutohet në sfond...',
                'from' => $from,
                'to' => $to,
            ]);
        }

        return response()->json([
            'success' => true,
            'synced' => false,
            'records' => 0,
            'message' => 'Live v24 mode active, no background sync needed.',
            'from' => $from,
            'to' => $to,
            'api_version' => config('meta.api_version', 'v24.0'),
        ]);
    }

    public function syncStatus(): JsonResponse
    {
        $status = Cache::get(MetaForceRefreshJob::STATUS_KEY, ['status' => 'idle']);

        return response()->json($status);
    }

    public function adsKpis(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $platform = $this->normalizeAdsPlatformFilter($request->get('platform', 'all'));
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->adsKpis($from, $to, $platform, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            Log::error('Meta v2 adsKpis failed: ' . $e->getMessage(), [
                'from' => $from,
                'to' => $to,
                'platform' => $platform,
                'nocache' => $noCache,
            ]);

            return response()->json($this->adsDefaultKpis());
        }
    }

    public function adsDaily(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $platform = $this->normalizeAdsPlatformFilter($request->get('platform', 'all'));
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->adsDailyReport($from, $to, $platform, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function adsCampaigns(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $platform = $this->normalizeAdsPlatformFilter($request->get('platform', 'all'));
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->adsCampaigns($from, $to, $platform, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function adsBreakdowns(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->adsBreakdowns($from, $to, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function igKpis(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->igKpis($from, $to, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            Log::error('Meta v2 igKpis failed: ' . $e->getMessage(), [
                'from' => $from,
                'to' => $to,
                'nocache' => $noCache,
            ]);

            return response()->json($this->igDefaultKpis());
        }
    }

    public function igDaily(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->igDailyReport($from, $to, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function igTopPosts(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $limit = max(1, min(50, (int) $request->get('limit', 12)));
        $type = $request->get('type');
        $type = is_string($type) && preg_match('/^[a-z_]+$/', $type) ? $type : null;
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->igTopPosts($from, $to, $limit, $type, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function igMessaging(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->igMessaging($from, $to, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function fbKpis(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        abort_unless(config('meta.features.facebook_module', false), 404);

        [$from, $to] = $this->resolveRange($request);
        $preset = $this->normalizePreset($request->get('preset'));
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->fbKpis($from, $to, $preset, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            Log::error('Meta v2 fbKpis failed: ' . $e->getMessage(), [
                'from' => $from,
                'to' => $to,
                'preset' => $preset,
                'nocache' => $noCache,
            ]);

            return response()->json($this->fbDefaultKpis());
        }
    }

    public function fbDaily(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        abort_unless(config('meta.features.facebook_module', false), 404);

        [$from, $to] = $this->resolveRange($request);
        $preset = $this->normalizePreset($request->get('preset'));
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->fbDailyReport($from, $to, $preset, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function fbTopPosts(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        abort_unless(config('meta.features.facebook_module', false), 404);

        [$from, $to] = $this->resolveRange($request);
        $limit = max(1, min(50, (int) $request->get('limit', 12)));
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->fbTopPosts($from, $to, $limit, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function fbMessaging(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        abort_unless(config('meta.features.facebook_module', false), 404);

        [$from, $to] = $this->resolveRange($request);
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->fbMessaging($from, $to, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // ─── TikTok Channel ──────────────────────────────────

    public function tiktokReport()
    {
        abort_unless(config('tiktok.features.tiktok_module', false), 404);

        return view('meta-marketing.tiktok', [
            'apiVersionV24' => config('meta.api_version', 'v24.0'),
        ]);
    }

    public function tiktokKpis(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        abort_unless(config('tiktok.features.tiktok_module', false), 404);

        [$from, $to] = $this->resolveRange($request);
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->tiktokKpis($from, $to, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            Log::error('Meta v2 tiktokKpis failed: ' . $e->getMessage(), [
                'from' => $from,
                'to' => $to,
            ]);

            return response()->json($this->tiktokDefaultKpis());
        }
    }

    public function tiktokDaily(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        abort_unless(config('tiktok.features.tiktok_module', false), 404);

        [$from, $to] = $this->resolveRange($request);
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->tiktokDailyReport($from, $to, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function tiktokCampaigns(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        abort_unless(config('tiktok.features.tiktok_module', false), 404);

        [$from, $to] = $this->resolveRange($request);
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->tiktokCampaigns($from, $to, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function tiktokBreakdowns(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        abort_unless(config('tiktok.features.tiktok_module', false), 404);

        [$from, $to] = $this->resolveRange($request);
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->tiktokBreakdowns($from, $to, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function tiktokTopVideos(Request $request, MetaMarketingV2ChannelService $service): JsonResponse
    {
        abort_unless(config('tiktok.features.tiktok_module', false), 404);

        [$from, $to] = $this->resolveRange($request);
        $limit = max(1, min(50, (int) $request->get('limit', 10)));
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->tiktokTopVideos($from, $to, $limit, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function resolveRange(Request $request): array
    {
        $defaultTo = Carbon::yesterday()->toDateString();
        $defaultFrom = Carbon::now()->subDays(30)->toDateString();

        $from = (string) $request->get('from', $defaultFrom);
        $to = (string) $request->get('to', $defaultTo);

        return [$from, $to];
    }

    private function normalizePreset(mixed $preset): ?string
    {
        if (!is_string($preset) || $preset === '') {
            return null;
        }

        return preg_match('/^[a-z_]+$/', $preset) ? $preset : null;
    }

    private function normalizeAdsPlatformFilter(mixed $platform): string
    {
        if (!config('meta.features.ads_platform_split', false)) {
            return 'all';
        }

        if (!is_string($platform)) {
            return 'all';
        }

        $platform = strtolower(trim($platform));
        return in_array($platform, ['all', 'facebook', 'instagram'], true) ? $platform : 'all';
    }

    private function defaultKpi(): array
    {
        return [
            'value' => 0,
            'change' => null,
        ];
    }

    private function adsDefaultKpis(): array
    {
        return [
            'spend' => $this->defaultKpi(),
            'impressions' => $this->defaultKpi(),
            'reach' => $this->defaultKpi(),
            'link_clicks' => $this->defaultKpi(),
            'ctr' => $this->defaultKpi(),
            'purchases' => $this->defaultKpi(),
            'revenue' => $this->defaultKpi(),
            'roas' => $this->defaultKpi(),
            'cpc' => $this->defaultKpi(),
            'cpm' => $this->defaultKpi(),
        ];
    }

    private function igDefaultKpis(): array
    {
        return [
            'reach' => $this->defaultKpi(),
            'views' => $this->defaultKpi(),
            'profile_views' => $this->defaultKpi(),
            'content_interactions' => $this->defaultKpi(),
            'post_engagement' => $this->defaultKpi(),
            'combined_link_clicks' => $this->defaultKpi(),
            'link_clicks' => $this->defaultKpi(),
            'ads_link_clicks' => $this->defaultKpi(),
            'new_threads' => $this->defaultKpi(),
            'conversations' => $this->defaultKpi(),
        ];
    }

    private function fbDefaultKpis(): array
    {
        return [
            'reach' => $this->defaultKpi(),
            'post_impressions' => $this->defaultKpi(),
            'page_views' => $this->defaultKpi(),
            'page_engagements' => $this->defaultKpi(),
            'ads_link_clicks' => $this->defaultKpi(),
            'post_engagement' => $this->defaultKpi(),
            'new_threads' => $this->defaultKpi(),
            'conversations' => $this->defaultKpi(),
        ];
    }

    private function tiktokDefaultKpis(): array
    {
        return [
            'spend' => $this->defaultKpi(),
            'impressions' => $this->defaultKpi(),
            'reach' => $this->defaultKpi(),
            'clicks' => $this->defaultKpi(),
            'ctr' => $this->defaultKpi(),
            'video_views' => $this->defaultKpi(),
            'purchases' => $this->defaultKpi(),
            'revenue' => $this->defaultKpi(),
            'roas' => $this->defaultKpi(),
            'cpc' => $this->defaultKpi(),
            'cpm' => $this->defaultKpi(),
            'engagement' => $this->defaultKpi(),
            'likes' => $this->defaultKpi(),
            'comments' => $this->defaultKpi(),
            'shares' => $this->defaultKpi(),
            'follows' => $this->defaultKpi(),
            'conversions' => $this->defaultKpi(),
            'cost_per_conversion' => $this->defaultKpi(),
            'add_to_cart' => $this->defaultKpi(),
            'initiate_checkout' => $this->defaultKpi(),
        ];
    }
}
