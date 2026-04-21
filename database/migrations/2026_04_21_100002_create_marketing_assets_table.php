<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing Assets — reusable media: stickers, music, fonts, logos, watermarks.
 *
 * Kept separate from daily_basket_post_media (which is post-scoped) because
 * assets are shared across templates and posts. Brand kit references a
 * subset of these via IDs in its JSON columns (music_library, logo_variants).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_assets', function (Blueprint $table) {
            $table->id();

            // sticker | music | font | logo | watermark | template-asset
            $table->string('kind', 30);

            $table->string('name', 180);
            $table->string('path', 500);
            $table->string('mime_type', 80)->nullable();

            // For music tracks
            $table->unsignedInteger('duration_seconds')->nullable();

            // Freeform tagging: {mood, genre, bpm, tags[]}
            $table->json('metadata')->nullable();

            // Cross-DB: DIS users
            $table->unsignedBigInteger('uploaded_by')->nullable();

            $table->timestamps();

            $table->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_assets');
    }
};
