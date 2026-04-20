<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Post-level media for Shporta Ditore.
 *
 * Separate from ContentMedia (which is scoped to published ContentPost rows).
 * A DailyBasketPost can accumulate 0..N media assets during the production /
 * editing stages; the widget in the panel adapts to the post_type:
 *   photo/video/reel/story → 1 slot
 *   carousel               → many slots, ordered by sort_order
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_basket_post_media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('daily_basket_post_id')
                ->constrained('daily_basket_posts')
                ->cascadeOnDelete();

            $table->string('disk', 20)->default('public');
            $table->string('path', 500);
            $table->string('original_filename', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('thumbnail_path', 500)->nullable();

            // Ordering within the post (matters for carousels).
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['daily_basket_post_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_basket_post_media');
    }
};
