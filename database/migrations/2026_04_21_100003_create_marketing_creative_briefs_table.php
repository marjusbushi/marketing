<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing Creative Briefs — the central record bridging AI, editor, and post.
 *
 * Schema is intentionally exhaustive from day one so that Faza 1 (AI Light,
 * fills only caption fields) evolves to Faza 2 (AI Smart, fills every field)
 * without any further migration. Unfilled fields stay NULL; editor treats
 * them as "user input required".
 *
 * Lifecycle:
 *   1. Created (manual or AI) linked to a daily_basket_post
 *   2. User edits in Polotno/Remotion, state JSON persisted here
 *   3. On finalize, a MarketingRenderJob is dispatched (for video) and its
 *      id stored in render_job_id
 *   4. When daily_basket_post transitions to stage=scheduling, the media
 *      from the latest render job (or Polotno export) feeds content_posts
 *
 * Note: render_job_id is NOT a DB foreign key to avoid the circular FK
 * between marketing_creative_briefs and marketing_render_jobs. The
 * relationship is enforced at the model layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_creative_briefs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('daily_basket_post_id')
                ->nullable()
                ->constrained('daily_basket_posts')
                ->nullOnDelete();

            $table->foreignId('template_id')
                ->nullable()
                ->constrained('marketing_templates')
                ->nullOnDelete();

            // photo | carousel | reel | video | story
            $table->string('post_type', 20);

            // "1:1", "4:5", "9:16", "16:9"
            $table->string('aspect', 10)->nullable();

            $table->unsignedInteger('duration_sec')->nullable();

            // ── AI Light fills these ────────────────────
            $table->text('caption_sq')->nullable();
            $table->text('caption_en')->nullable();
            $table->json('hashtags')->nullable();

            // ── AI Smart (Faza 2) fills these ───────────
            // Reference to marketing_assets.id or external track id
            $table->string('music_id', 100)->nullable();
            $table->json('script')->nullable();           // [{time, text, cta?}]
            $table->json('media_slots')->nullable();      // [{slot, media_id|product_image}]
            $table->dateTime('suggested_time')->nullable();

            // manual | ai-light | ai-smart
            $table->string('source', 20)->default('manual');
            $table->string('ai_prompt_version', 20)->nullable();

            // Full Polotno / Remotion editor state, compressed via Eloquent cast
            $table->json('state')->nullable();

            // Latest render job id (no FK, see class docblock)
            $table->unsignedBigInteger('render_job_id')->nullable();

            // Cross-DB: DIS users
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('daily_basket_post_id');
            $table->index('source');
            $table->index(['post_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_creative_briefs');
    }
};
