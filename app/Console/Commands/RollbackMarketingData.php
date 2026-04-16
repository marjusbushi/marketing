<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rollback: truncates all marketing-owned tables in the marketing database.
 *
 * Usage:
 *   php artisan marketing:rollback-data                    # interactive confirmation
 *   php artisan marketing:rollback-data --force             # skip confirmation
 *   php artisan marketing:rollback-data --tables=meta_tokens # specific tables only
 *
 * This does NOT touch the DIS database — source data remains intact.
 */
class RollbackMarketingData extends Command
{
    protected $signature = 'marketing:rollback-data
        {--force : Skip confirmation prompt}
        {--tables= : Comma-separated list of specific tables to truncate}';

    protected $description = 'Truncate marketing-owned tables in the marketing database (rollback)';

    /**
     * Reverse dependency order (children before parents).
     */
    protected array $truncateOrder = [
        // ── Influencer ───────────────────
        'influencers',

        // ── TikTok Analytics ─────────────
        'tiktok_sync_logs',
        'tiktok_ads_insights',
        'tiktok_campaigns',
        'tiktok_video_snapshots',
        'tiktok_videos',
        'tiktok_account_snapshots',
        'tiktok_accounts',
        'tiktok_tokens',

        // ── Meta Analytics ───────────────
        'meta_raw_events',
        'meta_sync_logs',
        'meta_period_totals',
        'meta_messaging_stats',
        'meta_post_insights',
        'meta_ig_insights',
        'meta_page_insights',
        'meta_ads_period_reach',
        'meta_ads_insights',
        'meta_ad_sets',
        'meta_campaigns',
        'meta_ad_accounts',
        'meta_tokens',

        // ── Content Planner ──────────────
        'content_share_links',
        'content_suggestions',
        'content_post_versions',
        'content_approval_steps',
        'content_comments',
        'content_post_labels',
        'content_labels',
        'content_post_media',
        'content_media',
        'content_post_platforms',
        'content_posts',
        'content_campaigns',
    ];

    public function handle(): int
    {
        $tables = $this->truncateOrder;

        if ($specificTables = $this->option('tables')) {
            $tables = array_intersect($tables, explode(',', $specificTables));
        }

        if (empty($tables)) {
            $this->error('No matching tables found.');
            return 1;
        }

        // Show what will be truncated
        $counts = [];
        foreach ($tables as $table) {
            if (Schema::connection('mysql')->hasTable($table)) {
                $count = DB::connection('mysql')->table($table)->count();
                $counts[] = [$table, $count];
            }
        }

        $this->table(['Table', 'Records'], $counts);

        $totalRecords = array_sum(array_column($counts, 1));
        $this->warn("This will DELETE {$totalRecords} records from " . count($counts) . " tables.");
        $this->warn('DIS source data will NOT be affected.');

        if (! $this->option('force') && ! $this->confirm('Proceed with rollback?')) {
            $this->info('Rollback cancelled.');
            return 0;
        }

        DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table) {
            if (Schema::connection('mysql')->hasTable($table)) {
                DB::connection('mysql')->table($table)->truncate();
                $this->line("  Truncated: {$table}");
            }
        }

        DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('Rollback complete. All marketing data has been removed.');
        $this->info('Run `php artisan marketing:migrate-data --execute` to re-import from DIS.');

        return 0;
    }
}
