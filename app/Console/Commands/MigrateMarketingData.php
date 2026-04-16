<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migrates marketing-owned data from DIS database to za-marketing database.
 *
 * Usage:
 *   php artisan marketing:migrate-data              # dry-run (default)
 *   php artisan marketing:migrate-data --execute     # actual migration
 *   php artisan marketing:migrate-data --tables=content_posts,influencers  # specific tables
 *   php artisan marketing:migrate-data --execute --fresh  # truncate + re-insert
 *
 * Prerequisites:
 *   - DIS database connection configured (config/database.php → 'dis')
 *   - Marketing database migrations have run (tables exist)
 *   - Both databases accessible from this app
 */
class MigrateMarketingData extends Command
{
    protected $signature = 'marketing:migrate-data
        {--execute : Actually perform the migration (default is dry-run)}
        {--fresh : Truncate marketing tables before inserting}
        {--tables= : Comma-separated list of specific tables to migrate}
        {--chunk=1000 : Batch size for chunked inserts}';

    protected $description = 'Migrate marketing-owned data from DIS to marketing database';

    /**
     * Tables in dependency order (parents before children).
     * Each entry: [table_name, has_soft_deletes]
     */
    protected array $migrationOrder = [
        // ── Content Planner ──────────────
        ['content_campaigns', true],
        ['content_posts', true],
        ['content_post_platforms', false],
        ['content_media', true],
        ['content_post_media', false],
        ['content_labels', false],
        ['content_post_labels', false],
        ['content_comments', true],
        ['content_approval_steps', false],
        ['content_post_versions', false],
        ['content_suggestions', false],
        ['content_share_links', false],

        // ── Meta Analytics ───────────────
        ['meta_tokens', false],
        ['meta_ad_accounts', false],
        ['meta_campaigns', false],
        ['meta_ad_sets', false],
        ['meta_ads_insights', false],
        ['meta_ads_period_reach', false],
        ['meta_page_insights', false],
        ['meta_ig_insights', false],
        ['meta_post_insights', false],
        ['meta_messaging_stats', false],
        ['meta_period_totals', false],
        ['meta_sync_logs', false],
        ['meta_raw_events', false],

        // ── TikTok Analytics ─────────────
        ['tiktok_tokens', false],
        ['tiktok_accounts', false],
        ['tiktok_account_snapshots', false],
        ['tiktok_videos', false],
        ['tiktok_video_snapshots', false],
        ['tiktok_campaigns', false],
        ['tiktok_ads_insights', false],
        ['tiktok_sync_logs', false],

        // ── Influencer ───────────────────
        ['influencers', true],
    ];

    public function handle(): int
    {
        $isDryRun = ! $this->option('execute');
        $isFresh = $this->option('fresh');
        $chunkSize = (int) $this->option('chunk');
        $specificTables = $this->option('tables')
            ? explode(',', $this->option('tables'))
            : null;

        $this->info($isDryRun ? '🔍 DRY RUN — no data will be written' : '🚀 EXECUTE MODE — data will be migrated');
        $this->newLine();

        // Filter tables if specific ones requested
        $tables = $this->migrationOrder;
        if ($specificTables) {
            $tables = array_filter($tables, fn ($t) => in_array($t[0], $specificTables));
            if (empty($tables)) {
                $this->error('No matching tables found.');
                return 1;
            }
        }

        $results = [];
        $totalMigrated = 0;
        $totalSkipped = 0;

        foreach ($tables as [$table, $hasSoftDeletes]) {
            // Check source table exists in DIS
            if (! $this->tableExistsOn('dis', $table)) {
                $this->warn("⏭  {$table}: not found in DIS database — skipping");
                $results[] = [$table, 'SKIPPED', 0, 0, 'Not in DIS'];
                $totalSkipped++;
                continue;
            }

            // Check target table exists in marketing
            if (! $this->tableExistsOn('mysql', $table)) {
                $this->warn("⏭  {$table}: not found in marketing database — skipping");
                $results[] = [$table, 'SKIPPED', 0, 0, 'Not in marketing'];
                $totalSkipped++;
                continue;
            }

            $sourceCount = DB::connection('dis')->table($table)->count();

            if ($sourceCount === 0) {
                $this->line("  {$table}: 0 records in DIS — nothing to migrate");
                $results[] = [$table, 'EMPTY', 0, 0, '-'];
                continue;
            }

            if ($isDryRun) {
                $targetCount = DB::connection('mysql')->table($table)->count();
                $this->line("  {$table}: {$sourceCount} records in DIS, {$targetCount} in marketing");
                $results[] = [$table, 'DRY RUN', $sourceCount, $targetCount, '-'];
                continue;
            }

            // Execute migration
            $this->info("  Migrating {$table} ({$sourceCount} records)...");

            if ($isFresh) {
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0');
                DB::connection('mysql')->table($table)->truncate();
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1');
            }

            $migrated = $this->migrateTable($table, $chunkSize);
            $targetCount = DB::connection('mysql')->table($table)->count();

            $status = $targetCount === $sourceCount ? '✅ OK' : '⚠️ MISMATCH';
            $results[] = [$table, $status, $sourceCount, $targetCount, $migrated . ' inserted'];
            $totalMigrated += $migrated;

            if ($targetCount !== $sourceCount) {
                $this->warn("    ⚠️  Count mismatch: DIS={$sourceCount}, Marketing={$targetCount}");
            }
        }

        // Summary table
        $this->newLine();
        $this->table(
            ['Table', 'Status', 'DIS Count', 'Marketing Count', 'Notes'],
            $results
        );

        $this->newLine();
        if ($isDryRun) {
            $this->info("Dry run complete. Use --execute to perform the migration.");
        } else {
            $this->info("Migration complete. {$totalMigrated} records migrated, {$totalSkipped} tables skipped.");
        }

        return 0;
    }

    /**
     * Migrate a single table using chunked inserts with upsert.
     */
    protected function migrateTable(string $table, int $chunkSize): int
    {
        $total = 0;
        $bar = $this->output->createProgressBar(
            DB::connection('dis')->table($table)->count()
        );
        $bar->start();

        // Get columns from the source table
        $columns = DB::connection('dis')
            ->getSchemaBuilder()
            ->getColumnListing($table);

        // Determine primary key for upsert
        $primaryKey = $this->getPrimaryKey($table);

        DB::connection('dis')
            ->table($table)
            ->orderBy($primaryKey)
            ->chunk($chunkSize, function ($rows) use ($table, $columns, $primaryKey, &$total, $bar) {
                $data = collect($rows)->map(fn ($row) => (array) $row)->toArray();

                // Upsert: insert or update on duplicate key
                DB::connection('mysql')
                    ->table($table)
                    ->upsert($data, [$primaryKey], $columns);

                $total += count($data);
                $bar->advance(count($data));
            });

        $bar->finish();
        $this->newLine();

        return $total;
    }

    /**
     * Get primary key column for a table.
     */
    protected function getPrimaryKey(string $table): string
    {
        // Pivot tables without id
        $compositeKeys = [
            'content_post_labels' => 'content_post_id',
            'content_post_media' => 'id',
        ];

        return $compositeKeys[$table] ?? 'id';
    }

    /**
     * Check if a table exists on a given connection.
     */
    protected function tableExistsOn(string $connection, string $table): bool
    {
        return Schema::connection($connection)->hasTable($table);
    }
}
