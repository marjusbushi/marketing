<?php

namespace App\Console\Commands;

use App\Services\Meta\MetaApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keep the IG DM webhook subscription healthy.
 *
 * Meta's webhook subscription silently drops from time to time — token rotation,
 * app secret change, app permission review, Meta-side transient. When it drops,
 * meta_ig_dm_events stops receiving rows and the dashboard's organic count
 * quietly decays to zero while Meta Business Suite keeps climbing. The symptom
 * that prompted this (2026-04-24): webhook activated 04-22, last event
 * 04-22 11:46 UTC, 48h of silence before anyone noticed.
 *
 * This command runs hourly and does three things:
 *   1. Reads current subscription from /PAGE_ID/subscribed_apps (GET).
 *   2. If the "messages" field is missing, calls /PAGE_ID/subscribed_apps (POST)
 *      with messages,messaging_postbacks,messaging_referrals (plural — see
 *      Task #1310 — singular is rejected on the Page endpoint).
 *   3. Checks the freshness of meta_ig_dm_events.received_at. If the subscription
 *      looks OK but no events have arrived in > 6h, log a warning so operators
 *      can investigate the Meta App Dashboard delivery logs.
 *
 * Safe to run concurrently (Graph API /subscribed_apps POST is idempotent).
 *
 * Exit code:
 *   0 — subscription healthy OR was unhealthy and repaired.
 *   1 — subscription is broken AND repair attempt failed (operator must act).
 *
 * Examples:
 *   php artisan meta:ig-webhook-heal
 *   php artisan meta:ig-webhook-heal --dry-run      # report, don't mutate
 *   php artisan meta:ig-webhook-heal --force        # re-subscribe even if OK
 */
class MetaIgWebhookHealCommand extends Command
{
    protected $signature = 'meta:ig-webhook-heal
        {--dry-run : Report current state without calling subscribed_apps POST}
        {--force : Re-subscribe even if messages field is already present}
        {--stale-threshold-hours=6 : Warn when last webhook event is older than this}';

    protected $description = 'Verify and repair the IG DM webhook subscription on /PAGE_ID/subscribed_apps.';

    private const REQUIRED_FIELDS = ['messages', 'messaging_postbacks', 'messaging_referrals'];

    public function handle(MetaApiService $api): int
    {
        $pageId = (string) config('meta.page_id');
        $pageToken = (string) config('meta.page_token');
        $apiVersion = (string) config('meta.api_version', 'v24.0');
        $baseUrl = rtrim((string) config('meta.base_url', 'https://graph.facebook.com'), '/');

        if ($pageId === '' || $pageToken === '') {
            $this->error('META_PAGE_ID or META_PAGE_TOKEN is not configured. Aborting.');
            Log::channel('meta-webhooks')->error('webhook-heal aborted: missing page_id or page_token');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $staleHours = max(1, (int) $this->option('stale-threshold-hours'));

        // ─── 1. Read current subscription ──────────────────────────────
        $this->info("Reading /{$pageId}/subscribed_apps …");
        try {
            $response = $api->getWithPageToken("{$pageId}/subscribed_apps", []);
        } catch (Throwable $e) {
            $this->error("Failed to read subscribed_apps: {$e->getMessage()}");
            Log::channel('meta-webhooks')->error('webhook-heal: GET subscribed_apps failed', [
                'error' => $e->getMessage(),
            ]);
            return self::FAILURE;
        }

        $subscribedFields = $this->extractSubscribedFields($response);
        $this->line('  Current subscribed fields: ' . (empty($subscribedFields) ? '(none)' : implode(', ', $subscribedFields)));

        $missing = array_values(array_diff(self::REQUIRED_FIELDS, $subscribedFields));
        $needsSubscribe = !empty($missing) || $force;

        if (!$needsSubscribe) {
            $this->info('  ✓ All required fields present (' . implode(', ', self::REQUIRED_FIELDS) . ')');
        } else {
            $reason = $force ? 'forced' : ('missing: ' . implode(', ', $missing));
            $this->warn("  Needs re-subscribe — {$reason}");
        }

        // ─── 2. Re-subscribe if needed ─────────────────────────────────
        if ($needsSubscribe && !$dryRun) {
            $ok = $this->subscribe($baseUrl, $apiVersion, $pageId, $pageToken);
            if (!$ok) {
                return self::FAILURE;
            }
        } elseif ($needsSubscribe && $dryRun) {
            $this->line('  [dry-run] would POST subscribed_apps with: ' . implode(',', self::REQUIRED_FIELDS));
        }

        // ─── 3. Freshness check on meta_ig_dm_events ───────────────────
        $this->checkFreshness($staleHours);

        return self::SUCCESS;
    }

    /**
     * Graph API returns { "data": [ { "subscribed_fields": [...] } ] } for the
     * current app's subscription. If multiple apps are subscribed, we match on
     * the configured app_id. When app_id is empty (local dev), take the first.
     */
    private function extractSubscribedFields(array $response): array
    {
        $rows = $response['data'] ?? [];
        if (empty($rows)) {
            return [];
        }

        $myAppId = (string) config('meta.app_id', '');
        $match = null;
        foreach ($rows as $row) {
            if ($myAppId !== '' && (string) ($row['id'] ?? '') === $myAppId) {
                $match = $row;
                break;
            }
        }
        $match ??= $rows[0];

        $fields = $match['subscribed_fields'] ?? [];
        return is_array($fields) ? array_values(array_map('strval', $fields)) : [];
    }

    private function subscribe(string $baseUrl, string $apiVersion, string $pageId, string $pageToken): bool
    {
        $this->info("POST /{$pageId}/subscribed_apps …");

        $url = "{$baseUrl}/{$apiVersion}/{$pageId}/subscribed_apps";

        try {
            $response = Http::timeout(30)
                ->asForm()
                ->post($url, [
                    'subscribed_fields' => implode(',', self::REQUIRED_FIELDS),
                    'access_token' => $pageToken,
                ]);

            if ($response->failed()) {
                $body = $response->json();
                $msg = $body['error']['message'] ?? $response->body();
                $code = $body['error']['code'] ?? $response->status();
                $this->error("  ✗ Subscribe failed [{$code}]: {$msg}");
                Log::channel('meta-webhooks')->error('webhook-heal: subscribe failed', [
                    'code' => $code,
                    'message' => $msg,
                ]);
                return false;
            }

            $success = (bool) ($response->json('success') ?? true);
            if (!$success) {
                $this->error('  ✗ Subscribe returned success=false: ' . $response->body());
                Log::channel('meta-webhooks')->error('webhook-heal: subscribe success=false', [
                    'body' => $response->body(),
                ]);
                return false;
            }

            $this->info('  ✓ Re-subscribed');
            Log::channel('meta-webhooks')->info('webhook-heal: subscription re-created', [
                'fields' => self::REQUIRED_FIELDS,
            ]);
            return true;
        } catch (Throwable $e) {
            $this->error("  ✗ Exception: {$e->getMessage()}");
            Log::channel('meta-webhooks')->error('webhook-heal: subscribe exception', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Soft check — log only. meta_ig_dm_events lives on the dis connection
     * (Decision #17). We do not fail the command on stale data because the
     * subscription being OK at Meta's end does not guarantee delivery; that
     * is a Meta-side problem to escalate, not a local fix.
     */
    private function checkFreshness(int $staleHours): void
    {
        try {
            $lastEvent = DB::connection('dis')->table('meta_ig_dm_events')
                ->where('platform', 'instagram')
                ->orderByDesc('received_at')
                ->value('received_at');
        } catch (Throwable $e) {
            $this->warn('  ! Could not query meta_ig_dm_events: ' . $e->getMessage());
            return;
        }

        if (!$lastEvent) {
            $this->warn('  ! meta_ig_dm_events is empty — no webhook events ever received.');
            Log::channel('meta-webhooks')->warning('webhook-heal: events table empty');
            return;
        }

        $age = Carbon::parse($lastEvent)->diffInMinutes(now());
        $threshold = $staleHours * 60;
        if ($age >= $threshold) {
            $hours = round($age / 60, 1);
            $this->warn("  ! Last webhook event was {$hours}h ago (threshold {$staleHours}h) — Meta may not be delivering.");
            Log::channel('meta-webhooks')->warning('webhook-heal: stale events', [
                'last_event' => (string) $lastEvent,
                'age_minutes' => $age,
                'threshold_minutes' => $threshold,
            ]);
        } else {
            $this->line("  ✓ Last webhook event {$age} min ago (fresh)");
        }
    }
}
