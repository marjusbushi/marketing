<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the millisecond offset into the source video where the user
 * captured the cover frame. Two consumers:
 *
 *   1. Local <video> playback (Flare UI) — set video.currentTime to
 *      this value before autoplay so the first frame the user sees is
 *      the cover, not the video's actual frame 0.
 *   2. Meta IG REELS publish — pass as `thumb_offset` so Meta uses the
 *      same frame as the cover both on the grid AND as the initial
 *      playback frame.
 *
 * Null when the user uploaded a custom JPG/PNG cover (no source frame).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_media', function (Blueprint $table) {
            $table->unsignedInteger('cover_timestamp_ms')->nullable()->after('cover_path');
        });
        Schema::table('daily_basket_post_media', function (Blueprint $table) {
            $table->unsignedInteger('cover_timestamp_ms')->nullable()->after('cover_path');
        });
    }

    public function down(): void
    {
        Schema::table('content_media', function (Blueprint $table) {
            $table->dropColumn('cover_timestamp_ms');
        });
        Schema::table('daily_basket_post_media', function (Blueprint $table) {
            $table->dropColumn('cover_timestamp_ms');
        });
    }
};
