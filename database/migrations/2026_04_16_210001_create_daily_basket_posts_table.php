<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily Basket Posts — the actual "unit of work" in the basket.
 *
 * Each post:
 *  • belongs to one DailyBasket
 *  • may contain 1+ products (via pivot table daily_basket_post_products)
 *  • moves through 5 stages: planning → production → editing → scheduling → published
 *  • optionally generates a content_posts row when it reaches the scheduling stage
 *
 * content_posts lives in THIS database (za_marketing), so content_post_id
 * is a normal FK. assigned_to points to DIS users, so it follows the
 * cross-DB pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_basket_posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('daily_basket_id')
                ->constrained('daily_baskets')
                ->cascadeOnDelete();

            // photo | video | reel | carousel | story
            $table->string('post_type', 20)->index();

            // planning | production | editing | scheduling | published
            $table->string('stage', 20)->default('planning')->index();

            $table->string('title', 255);

            // ── Stage 1: Planning ────────────────────────
            $table->string('reference_url', 500)->nullable();
            $table->text('reference_notes')->nullable();

            // ── Stage 2: Production ──────────────────────
            $table->text('production_brief')->nullable();

            // ── Stage 3: Editing ─────────────────────────
            $table->text('caption')->nullable();
            $table->text('hashtags')->nullable();

            // ── Stage 4: Scheduling ──────────────────────
            // Which social platforms this post targets: e.g. ["instagram","tiktok"]
            $table->json('target_platforms')->nullable();
            $table->dateTime('scheduled_for')->nullable()->index();

            // ── Stage 5: Published ───────────────────────
            // When this basket post has been handed off to Content Planner,
            // we store the FK so we can watch its status and link back.
            $table->foreignId('content_post_id')
                ->nullable()
                ->constrained('content_posts')
                ->nullOnDelete();

            // ── Ownership & misc ─────────────────────────
            // Cross-DB: DIS users
            $table->unsignedBigInteger('assigned_to')->nullable()->index();

            // low | normal | high | urgent
            $table->string('priority', 10)->default('normal')->index();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['daily_basket_id', 'stage']);
            $table->index(['stage', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_basket_posts');
    }
};
