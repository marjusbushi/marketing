<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link daily_basket_posts to their Visual Studio creative brief.
 *
 * A post may or may not have a brief (legacy posts and imports have none).
 * When present, the brief supersedes ad-hoc fields: its caption_sq/en feed
 * into content_posts at scheduling time, its render output auto-attaches
 * to daily_basket_post_media.
 *
 * nullOnDelete: removing a brief (rare — soft-deleted) does not cascade
 * into the post itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_basket_posts', function (Blueprint $table) {
            $table->foreignId('creative_brief_id')
                ->nullable()
                ->after('content_post_id')
                ->constrained('marketing_creative_briefs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_basket_posts', function (Blueprint $table) {
            $table->dropForeign(['creative_brief_id']);
            $table->dropColumn('creative_brief_id');
        });
    }
};
