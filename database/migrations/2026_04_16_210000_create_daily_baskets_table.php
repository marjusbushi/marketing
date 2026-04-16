<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily Basket — one row per (collection, date).
 *
 * A "collection" is a DistributionWeek living in the DIS database, so
 * `distribution_week_id` is stored as an unsignedBigInteger with an index
 * (no FK constraint — cross-DB). The Eloquent relationship resolves via
 * a model with $connection = 'dis'.
 *
 * `created_by` follows the same cross-DB pattern as content_posts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_baskets', function (Blueprint $table) {
            $table->id();

            // Cross-DB reference to DIS distribution_weeks
            $table->unsignedBigInteger('distribution_week_id')->index();

            // The specific day inside the collection window (unique per week)
            $table->date('date');

            // draft | active | closed
            $table->string('status', 20)->default('draft')->index();

            $table->text('notes')->nullable();

            // Cross-DB reference to DIS users
            $table->unsignedBigInteger('created_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            // One basket per (collection, date)
            $table->unique(['distribution_week_id', 'date'], 'daily_baskets_week_date_unique');

            // Fast lookup by date alone (e.g. "today across all collections")
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_baskets');
    }
};
