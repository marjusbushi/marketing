<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Media per post te importuar nga Meta (Instagram + Facebook).
 *
 * meta_post_insights ruante VETEM nje media_url per post, por ne realitet
 * nje post mund te jete carousel (N imazhe), video (cover + video_url), ose
 * foto e vetme. Kjo tabel i ruan te gjithe media ne nje marredhenie has-many.
 *
 * local_path mban file-in e downloaded (Storage::disk('public')) keshtu qe
 * imazhet mbeten te dukshme pas expiry-t te token-eve IG/FB (~1-2h).
 */
return new class extends Migration
{
    // meta_post_insights lives on the DIS database connection (shared with
    // the monolith). The new media table must sit alongside it so the FK
    // works and the hasMany relation loads.
    protected $connection = 'dis';

    public function up(): void
    {
        Schema::connection($this->connection)->create('meta_post_media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('meta_post_insight_id')
                ->constrained('meta_post_insights')
                ->cascadeOnDelete();

            // Position brenda carousel (0-based). Per foto/video te vetme = 0.
            $table->unsignedInteger('position')->default(0);

            // IMAGE / VIDEO (CAROUSEL_ALBUM eshte nivel post-i, jo media item-i)
            $table->string('media_type', 20);

            // IG/FB media ID per cdo item (per carousel fetch-ojme children).
            // Nullable — jo te gjitha feed-et e kthejne.
            $table->string('ig_media_id', 50)->nullable()->index();

            // URL origjinal nga Meta CDN (per reference; skadon shpejt)
            $table->string('original_url', 1000)->nullable();

            // Per video: URL i video-s (jo cover)
            $table->string('video_url', 1000)->nullable();

            // Cover/thumbnail URL (per video — original thumbnail nga IG)
            $table->string('thumbnail_url', 1000)->nullable();

            // Download local — i sigurte pas expiry te token-eve
            $table->string('local_path', 500)->nullable();
            $table->string('local_disk', 30)->nullable();
            $table->string('local_thumbnail_path', 500)->nullable();

            // Metadata
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('downloaded_at')->nullable();

            $table->timestamps();

            // Queries: load all media per post ne renditje
            $table->index(['meta_post_insight_id', 'position'], 'idx_post_position');

            // Post + media unique — zero duplicate ne sync rerun
            $table->unique(['meta_post_insight_id', 'position'], 'uniq_post_position');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('meta_post_media');
    }
};
