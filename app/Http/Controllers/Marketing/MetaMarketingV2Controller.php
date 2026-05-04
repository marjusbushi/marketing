<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Middleware\MetaMarketingCache;
use App\Jobs\MetaForceRefreshJob;
use App\Models\Meta\MetaSyncLog;
use App\Services\Meta\MetaDataResolverService;
use App\Services\Meta\MetaMarketingV2ReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MetaMarketingV2Controller extends Controller
{
    /**
     * Show the total Meta dashboard (Graph API v24, live).
     */
    public function index()
    {
        $lastSync = MetaSyncLog::where('status', 'success')
            ->latest()
            ->first();

        return view('meta-marketing.total', [
            'lastSync' => $lastSync,
            'apiVersionV24' => config('meta.api_version', 'v24.0'),
        ]);
    }

    /**
     * API: Total Meta Report KPIs (v24 live).
     */
    public function totalKpis(Request $request, MetaMarketingV2ReportService $service): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $preset = $this->normalizePreset($request->get('preset'));
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->totalKpis($from, $to, $preset, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (\Throwable $e) {
            Log::error('Meta v2 totalKpis failed: ' . $e->getMessage(), [
                'from' => $from,
                'to' => $to,
                'preset' => $preset,
                'nocache' => $noCache,
            ]);

            return response()->json([
                'total_reach' => $this->defaultKpi(),
                'total_impressions' => $this->defaultKpi(),
                'total_page_views' => $this->defaultKpi(),
                'total_engagement' => $this->defaultKpi(),
                'combined_link_clicks' => $this->defaultKpi(),
                'total_link_clicks' => $this->defaultKpi(),
                'ads_link_clicks' => $this->defaultKpi(),
                'new_threads' => $this->defaultKpi(),
                'conversations' => $this->defaultKpi(),
                'ads_spend' => $this->defaultKpi(),
                'ads_revenue' => $this->defaultKpi(),
                'roas' => $this->defaultKpi(),
                'fb_reach' => $this->defaultKpi(),
                'ig_reach' => $this->defaultKpi(),
            ]);
        }
    }

    /**
     * API: Total Meta Report daily charts (v24 live).
     */
    public function totalDaily(Request $request, MetaMarketingV2ReportService $service): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->totalDaily($from, $to, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Total Meta Report channel comparison (v24 live).
     */
    public function totalComparison(Request $request, MetaMarketingV2ReportService $service): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $preset = $this->normalizePreset($request->get('preset'));
        $noCache = $request->boolean('nocache');

        try {
            $data = $service->totalComparison($from, $to, $preset, $noCache);
            return response()->json($data)->header('X-Meta-Cache', $service->wasCacheHit() ? 'HIT' : 'MISS');
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rifresko: dispatch full background sync from Meta API.
     * Returns immediately — frontend polls syncStatus for completion.
     */
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

            $channel = $request->input('channel');
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

    private function defaultKpi(): array
    {
        return [
            'value' => 0,
            'change' => null,
        ];
    }
}
