<?php

namespace App\Console\Commands;

use App\Http\Controllers\Marketing\MetaMarketingV2ChannelsController;
use App\Http\Controllers\Marketing\MetaMarketingV2Controller;
use App\Services\Meta\MetaMarketingV2ChannelService;
use App\Services\Meta\MetaMarketingV2ReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetaWarmCacheCommand extends Command
{
    protected $signature = 'meta:warm-cache
        {--presets= : Comma-separated presets to warm (default: common presets)}
        {--refresh-today : Only refresh today\'s data from Meta API}';

    protected $description = 'Pre-warm Meta Marketing dashboard caches for common date ranges';

    /**
     * All API endpoint methods to warm, grouped by controller type.
     */
    private array $totalEndpoints = ['totalKpis', 'totalDaily', 'totalComparison'];
    private array $channelEndpoints = [
        'ads' => ['adsKpis', 'adsDaily', 'adsCampaigns', 'adsBreakdowns'],
        'instagram' => ['igKpis', 'igDaily', 'igTopPosts', 'igMessaging'],
        'facebook' => ['fbKpis', 'fbDaily', 'fbTopPosts', 'fbMessaging'],
    ];

    public function handle(): int
    {
        $dbFirst = config('meta.features.db_first_mode', false);

        if ($this->option('refresh-today')) {
            return $this->refreshToday();
        }

        if ($dbFirst) {
            return $this->warmDbFirst();
        }

        return $this->warmLegacy();
    }

    /**
     * DB-first warming: call service methods directly (resolver populates DB + cache).
     * Much faster than legacy — DB queries instead of Meta API calls.
     */
    private function warmDbFirst(): int
    {
        $this->info('Warming Meta Marketing caches (DB-first mode)...');
        $startTime = microtime(true);

        $presets = $this->resolvePresets();
        $channelService = app(MetaMarketingV2ChannelService::class);
        $reportService = app(MetaMarketingV2ReportService::class);
        $warmed = 0;
        $failed = 0;

        foreach ($presets as $preset) {
            [$from, $to] = $this->resolvePresetDates($preset);
            if (! $from || ! $to) {
                continue;
            }

            $this->info("  {$preset} ({$from} → {$to})");

            // Warm total report (triggers resolver gap-fill + cache)
            foreach (['totalKpis', 'totalDaily'] as $method) {
                try {
                    $method === 'totalKpis'
                        ? $reportService->totalKpis($from, $to, $preset)
                        : $reportService->totalDaily($from, $to);
                    $warmed++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn("    FAIL: {$method} — {$e->getMessage()}");
                }
            }

            // Warm channel endpoints
            $channelMethods = [
                ['adsKpis', fn () => $channelService->adsKpis($from, $to)],
                ['adsDailyReport', fn () => $channelService->adsDailyReport($from, $to)],
                ['igKpis', fn () => $channelService->igKpis($from, $to)],
                ['igDailyReport', fn () => $channelService->igDailyReport($from, $to)],
                ['fbKpis', fn () => $channelService->fbKpis($from, $to, $preset)],
                ['fbDailyReport', fn () => $channelService->fbDailyReport($from, $to, $preset)],
            ];

            foreach ($channelMethods as [$name, $callable]) {
                try {
                    $callable();
                    $warmed++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn("    FAIL: {$name} — {$e->getMessage()}");
                }
            }
        }

        $duration = round(microtime(true) - $startTime, 2);
        $this->newLine();
        $this->info("Done. Warmed: {$warmed}, Failed: {$failed}, Duration: {$duration}s");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Refresh today's + yesterday's data from Meta API and update DB.
     * Calls actual sync services (not resolver) to ensure fresh API data.
     * Also busts service-level caches so dashboard shows fresh data.
     */
    private function refreshToday(): int
    {
        $this->info('Refreshing today + yesterday data from Meta API...');
        $startTime = microtime(true);
        $yesterday = Carbon::yesterday()->toDateString();
        $today = Carbon::today()->toDateString();
        $failed = 0;

        $syncService = app(\App\Services\Meta\MetaSyncService::class);

        // Sync each data type for yesterday→today (2 days) via actual API calls
        $types = ['ads', 'page', 'ig', 'messaging'];

        foreach ($types as $type) {
            try {
                $results = $syncService->syncType($type, $yesterday, $today);
                $records = collect($results)->sum('records');
                $status = collect($results)->every(fn ($r) => $r['status'] === 'success') ? 'OK' : 'partial';
                $this->info("  {$status}: {$type} — {$records} records");
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("  FAIL: {$type} — {$e->getMessage()}");
            }
        }

        // Bust service-level caches so dashboard picks up fresh data
        \App\Http\Middleware\MetaMarketingCache::bustCache();

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("Done in {$duration}s — caches busted");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Legacy warming: calls controller endpoints directly (full Meta API calls).
     */
    private function warmLegacy(): int
    {
        $this->info('Warming Meta Marketing dashboard caches (legacy mode)...');
        $startTime = microtime(true);

        $presets = $this->resolvePresets();
        $totalController = app(MetaMarketingV2Controller::class);
        $channelController = app(MetaMarketingV2ChannelsController::class);
        $reportService = app(MetaMarketingV2ReportService::class);
        $channelService = app(MetaMarketingV2ChannelService::class);
        $warmed = 0;
        $failed = 0;

        foreach ($presets as $preset) {
            [$from, $to] = $this->resolvePresetDates($preset);

            if (!$from || !$to) {
                $this->warn("  Skipping unknown preset: {$preset}");
                continue;
            }

            $this->info("  Preset: {$preset} ({$from} → {$to})");

            // Warm total report endpoints
            foreach ($this->totalEndpoints as $method) {
                try {
                    $params = ['from' => $from, 'to' => $to];
                    if (in_array($method, ['totalKpis', 'totalComparison'])) {
                        $params['preset'] = $preset;
                    }

                    $request = Request::create(
                        "/management/meta-marketing/api/{$method}",
                        'GET',
                        $params
                    );

                    $totalController->$method($request, $reportService);
                    $warmed++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning("Meta cache warm failed for {$method} ({$preset}): " . $e->getMessage());
                    $this->warn("    FAIL: {$method} — {$e->getMessage()}");
                }
            }

            // Warm channel report endpoints
            foreach ($this->channelEndpoints as $group => $methods) {
                foreach ($methods as $method) {
                    try {
                        $params = ['from' => $from, 'to' => $to];
                        if (in_array($method, ['fbKpis'])) {
                            $params['preset'] = $preset;
                        }

                        $request = Request::create(
                            "/management/meta-marketing/api/{$method}",
                            'GET',
                            $params
                        );

                        $channelController->$method($request, $channelService);
                        $warmed++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning("Meta cache warm failed for {$method} ({$preset}): " . $e->getMessage());
                        $this->warn("    FAIL: {$method} — {$e->getMessage()}");
                    }
                }
            }
        }

        $duration = round(microtime(true) - $startTime, 2);
        $this->newLine();
        $this->info("Done. Warmed: {$warmed}, Failed: {$failed}, Duration: {$duration}s");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolvePresets(): array
    {
        return $this->option('presets')
            ? explode(',', $this->option('presets'))
            : ['this_month', 'last_month', 'this_week', 'yesterday', 'this_quarter', 'ytd'];
    }

    private function resolvePresetDates(string $preset): array
    {
        $today = Carbon::today();

        return match ($preset) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'yesterday' => [$today->copy()->subDay()->toDateString(), $today->copy()->subDay()->toDateString()],
            'this_week' => [$today->copy()->startOfWeek(Carbon::MONDAY)->toDateString(), $today->toDateString()],
            'last_week' => [
                $today->copy()->subWeek()->startOfWeek(Carbon::MONDAY)->toDateString(),
                $today->copy()->subWeek()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            ],
            'this_month' => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
            'last_month' => [
                $today->copy()->subMonth()->startOfMonth()->toDateString(),
                $today->copy()->subMonth()->endOfMonth()->toDateString(),
            ],
            'this_quarter' => [
                $today->copy()->firstOfQuarter()->toDateString(),
                $today->toDateString(),
            ],
            'last_quarter' => [
                $today->copy()->subQuarter()->firstOfQuarter()->toDateString(),
                $today->copy()->subQuarter()->lastOfQuarter()->toDateString(),
            ],
            'this_year' => [$today->copy()->startOfYear()->toDateString(), $today->toDateString()],
            'last_year' => [
                $today->copy()->subYear()->startOfYear()->toDateString(),
                $today->copy()->subYear()->endOfYear()->toDateString(),
            ],
            'ytd' => [$today->copy()->startOfYear()->toDateString(), $today->toDateString()],
            default => [null, null],
        };
    }
}
