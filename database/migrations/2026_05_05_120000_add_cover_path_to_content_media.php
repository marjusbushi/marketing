<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Custom Reel cover (user-picked frame or uploaded JPG). Distinct from
     * `thumbnail_path` which is the auto-generated preview ffmpeg extracts
     * at upload time. When set, this is the URL we send to Meta as
     * `cover_url` on REELS / VIDEO container creation.
     */
    public function up(): void
    {
        Schema::table('content_media', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('thumbnail_path');
        });
    }

    public function down(): void
    {
        Schema::table('content_media', function (Blueprint $table) {
            $table->dropColumn('cover_path');
        });
    }
};
