<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the cover_path on content_media so the cover picker works
 * uniformly from the Shporta Ditore post sheet too. When a daily-basket
 * media row was attached from the library (same `path` as a
 * content_media row), the controller will also patch the underlying
 * content_media so a downstream IG publish from the planner picks up
 * the same cover_url.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_basket_post_media', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('thumbnail_path');
        });
    }

    public function down(): void
    {
        Schema::table('daily_basket_post_media', function (Blueprint $table) {
            $table->dropColumn('cover_path');
        });
    }
};
