<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing Render Jobs — tracking rows for video render pipeline.
 *
 * A job is enqueued when the user clicks "Finalize video" in the editor.
 * The Horizon worker (queue 'video-render') picks it up and delegates to
 * the Node Remotion renderer. When the renderer posts back (or polling
 * sees completion), the output path and metadata are written here and
 * auto-attached to daily_basket_post_media via an event listener.
 *
 * Retries live in the Horizon job itself (tries=3, exponential backoff);
 * this table records the outcome of each attempt's final state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_render_jobs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('creative_brief_id')
                ->constrained('marketing_creative_briefs')
                ->cascadeOnDelete();

            // queued | rendering | completed | failed
            $table->string('status', 20)->default('queued');

            $table->string('output_path', 500)->nullable();
            $table->string('output_thumbnail', 500)->nullable();
            $table->unsignedInteger('output_duration_seconds')->nullable();
            $table->unsignedBigInteger('output_size_bytes')->nullable();

            $table->text('error_message')->nullable();

            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('creative_brief_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_render_jobs');
    }
};
