<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decision #14 (Canva Connect + CapCut manual) removed the Remotion-based
 * render pipeline. The `marketing_render_jobs` table and the
 * `marketing_creative_briefs.render_job_id` column were created for that
 * pipeline and are now orphaned.
 *
 * This migration drops both. Guarded with `has*` checks so re-running on
 * environments that never ran the originals is a no-op.
 *
 * `down()` recreates the schema approximately (engine + status enums,
 * nullable output refs) — the original structure is preserved in the
 * `2026_04_21_100004_create_marketing_render_jobs_table` migration for
 * reference, and we mirror the shape here so rollbacks stay self-contained.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('marketing_creative_briefs', 'render_job_id')) {
            Schema::table('marketing_creative_briefs', function (Blueprint $table) {
                $table->dropColumn('render_job_id');
            });
        }

        Schema::dropIfExists('marketing_render_jobs');
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketing_render_jobs')) {
            Schema::create('marketing_render_jobs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('creative_brief_id')
                    ->nullable()
                    ->constrained('marketing_creative_briefs')
                    ->nullOnDelete();
                $table->string('status', 20)->default('pending');
                $table->string('engine', 20)->default('remotion');
                $table->json('input')->nullable();
                $table->string('output_path', 500)->nullable();
                $table->string('error_message', 1000)->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'engine']);
                $table->index('creative_brief_id');
            });
        }

        if (!Schema::hasColumn('marketing_creative_briefs', 'render_job_id')) {
            Schema::table('marketing_creative_briefs', function (Blueprint $table) {
                $table->unsignedBigInteger('render_job_id')->nullable()->after('state');
            });
        }
    }
};
