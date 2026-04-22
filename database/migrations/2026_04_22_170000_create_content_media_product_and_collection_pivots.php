<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media Library v3 — linking media to DIS products (item_groups) and
 * collections (distribution_weeks).
 *
 * Cross-DB: item_group_id and distribution_week_id live in DIS, so no FK
 * constraint on those columns — the same pattern daily_baskets uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_media_item_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_media_id')->constrained('content_media')->cascadeOnDelete();
            $table->unsignedBigInteger('item_group_id')->index();
            $table->timestamp('created_at')->nullable();

            $table->unique(['content_media_id', 'item_group_id'], 'cmig_media_group_unique');
        });

        Schema::create('content_media_distribution_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_media_id')->constrained('content_media')->cascadeOnDelete();
            $table->unsignedBigInteger('distribution_week_id')->index();
            $table->timestamp('created_at')->nullable();

            $table->unique(['content_media_id', 'distribution_week_id'], 'cmdw_media_week_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_media_distribution_weeks');
        Schema::dropIfExists('content_media_item_groups');
    }
};
