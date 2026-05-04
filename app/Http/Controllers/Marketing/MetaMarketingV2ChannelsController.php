<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Middleware\MetaMarketingCache;
use App\Jobs\MetaForceRefreshJob;
use App\Models\Meta\MetaMessagingStat;
use App\Models\Meta\MetaSyncLog;
use App\Services\Meta\MetaDataResolverService;
use App\Services\Meta\MetaMarketingV2ChannelService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    /**
     * Diagnostic endpoint — explains where the "Kontakte Aktive" number
     * comes from so we can pinpoint why the dashboard count drifts from
     * Meta Business Suite ("Messaging conversations started").
     *
     * Returns the three inputs used by the aggregation:
     * 1. organic_new_conversations — from MetaMessagingStat.new_conversations
     * 2. paid_messaging_conversations — summed from meta_ads_insights.platform_breakdown.instagram.messaging_conversations
     * 3. last sync timestamps for both sources
     */
    public function igDmDebug(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        try {
            $webhookStart = (string) config('meta.ig_webhook_start_date', '');
            $webhookActive = $webhookStart !== '';

            $organicRows = MetaMessagingStat::where('platform', 'instagram')
                ->whereBetween('date', [$from, $to])
                ->orderBy('date')
                ->get();

            $paidJsonPath = '$."instagram".messaging_conversations';
            $paidRows = DB::connection('dis')
                ->table('meta_ads_insights')
                ->whereBetween('date', [$from, $to])
                ->whereNotNull('platform_breakdown')
                ->selectRaw('date, SUM(JSON_EXTRACT(platform_breakdown, ?)) as mc', [$paidJsonPath])
                ->groupBy('date')
                ->pluck('mc', 'date');

            // Webhook-era per-day counts, bucketed by Tirana local date.
            // Delegates timezone math to MetaDataResolverService::tiranaDayWindowUtc
            // so this diagnostic endpoint matches the live resolver byte-for-byte
            // (same DST-aware offset). Prior version hardcoded +01:00 and dropped
            // ~1h of DMs at day edges during CEST (Apr-Oct).
            $webhookByDate = [];
            if ($webhookActive) {
                $window = \App\Services\Meta\MetaDataResolverService::tiranaDayWindowUtc($from, $to);

                $webhookRows = DB::connection('dis')->table('meta_ig_dm_events')
                    ->selectRaw("DATE(CONVERT_TZ(received_at, '+00:00', ?)) as d, COUNT(*) as c", [$window['offset']])
                    ->where('platform', 'instagram')
                    ->where('is_from_page', false)
                    ->where('is_first_of_thread', true)
                    ->whereNull('ad_id')
                    ->whereBetween('received_at', [$window['from_utc'], $window['to_utc']])
                    ->groupByRaw("DATE(CONVERT_TZ(received_at, '+00:00', ?))", [$window['offset']])
                    ->get();

                foreach ($webhookRows as $r) {
                    $webhookByDate[(string) $r->d] = (int) $r->c;
                }
            }

            $daily = [];
            $organicTotal = 0;
            $paidTotal = 0;
            $webhookTotal = 0;
            $sampleTotal = 0;
            $webhookDays = 0;
            $totalDays = 0;

            $startC = $webhookActive ? Carbon::parse($webhookStart)->startOfDay() : null;

            for ($d = Carbon::parse($from)->copy(); $d->lte(Carbon::parse($to)); $d->addDay()) {
                $date = $d->toDateString();
                $sampleCount = (int) ($organicRows->firstWhere(fn ($r) => (string) ($r->date instanceof \DateTimeInterface ? $r->date->format('Y-m-d') : $r->date) === $date)?->new_conversations ?? 0);
                $paid = (int) ($paidRows[$date] ?? 0);
                $webhookCount = $webhookByDate[$date] ?? 0;

                $isWebhookEra = $webhookActive && $d->gte($startC);
                $effectiveSource = $isWebhookEra ? 'webhook' : 'sample';
                $effectiveOrganic = $isWebhookEra ? $webhookCount : $sampleCount;

                $daily[] = [
                    'date' => $date,
                    'effective_source' => $effectiveSource,
                    'webhook_count' => $webhookCount,
                    'sample_count' => $sampleCount,
                    'organic' => $effectiveOrganic,
                    'paid' => $paid,
                    'total' => $effectiveOrganic + $paid,
                ];

                $organicTotal += $effectiveOrganic;
                $paidTotal += $paid;
                $webhookTotal += $webhookCount;
                $sampleTotal += $sampleCount;
                $totalDays++;
                if ($isWebhookEra) {
                    $webhookDays++;
                }
            }

            $lastMessagingSync = MetaMessagingStat::where('platform', 'instagram')
                ->orderByDesc('synced_at')
                ->value('synced_at');

            $lastAdsSync = DB::connection('dis')
                ->table('meta_ads_insights')
                ->orderByDesc('synced_at')
                ->value('synced_at');

            $lastWebhookEvent = $webhookActive
                ? DB::connection('dis')->table('meta_ig_dm_events')
                    ->where('platform', 'instagram')
                    ->orderByDesc('received_at')
                    ->value('received_at')
                : null;

            return response()->json([
                'from' => $from,
                'to' => $to,
                'webhook_start_date' => $webhookStart ?: null,
                'webhook_coverage_pct' => $totalDays > 0 ? round($webhookDays / $totalDays * 100, 1) : 0.0,
                'organic_total' => $organicTotal,
                'paid_total' => $paidTotal,
                'combined_total' => $organicTotal + $paidTotal,
                'webhook_total' => $webhookTotal,
                'sample_total' => $sampleTotal,
                'last_messaging_sync' => $lastMessagingSync,
                'last_ads_sync' => $lastAdsSync,
                'last_webhook_event' => $lastWebhookEvent,
                'daily' => $daily,
                'notes' => [
                    'organic_source' => $webhookActive
                        ? 'meta_ig_dm_events (webhook, is_first_of_thread=true, ad_id IS NULL) for dates >= ' . $webhookStart . '; MetaMessagingStat (Conversations API sample) for earlier dates'
                        : 'MetaMessagingStat.new_conversations (from Conversations API, folder=instagram)',
                    'paid_source' => 'meta_ads_insights.platform_breakdown.instagram.messaging_conversations (from Ads API)',
                    'dashboard_formula' => 'combined_total = organic_total + paid_total',
                    'meta_timezone' => 'Meta Business Suite reports in Pacific Time; our query uses Europe/Tirana local dates (CEST/+02:00 in summer, CET/+01:00 in winter, DST-aware). Daily totals will not match Meta BS exactly because the day windows shift by 9-10 hours depending on DST. For a 1:1 check, compare rolling 7/30-day periods instead of single days.',
                ],
            ]);
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
        // `today()` jo `yesterday()` -- dashboard duhet te tregoje partial
        // metrikat e dites se sotme; cron-i hourly i freskon gjate dites.
        $defaultTo = Carbon::today()->toDateString();
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
