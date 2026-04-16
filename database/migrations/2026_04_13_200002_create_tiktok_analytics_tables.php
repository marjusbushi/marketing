<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated TikTok analytics schema — organic + ads.
 * All self-contained, no cross-DB references.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Tokens ────────────────────────────────────────────
        Schema::create('tiktok_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('token_type', 30)->default('organic'); // organic, ads
            $table->string('open_id', 100)->index();
            $table->string('union_id', 100)->nullable();
            $table->string('advertiser_id', 40)->nullable();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->json('scopes')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['token_type', 'is_active'], 'idx_tt_token_type_active');
        });

        // ── Accounts ──────────────────────────────────────────
        Schema::create('tiktok_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('open_id', 100)->unique();
            $table->string('union_id', 100)->nullable();
            $table->string('display_name', 200)->nullable();
            $table->string('username', 200)->nullable();
            $table->text('avatar_url')->nullable();
            $table->text('bio_description')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->string('profile_deep_link', 500)->nullable();
            $table->bigInteger('follower_count')->default(0);
            $table->bigInteger('following_count')->default(0);
            $table->bigInteger('likes_count')->default(0);
            $table->bigInteger('video_count')->default(0);
            $table->timestamps();
        });

        // ── Account Snapshots (daily) ─────────────────────────
        Schema::create('tiktok_account_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tiktok_account_id')->constrained('tiktok_accounts')->cascadeOnDelete();
            $table->date('date');
            $table->bigInteger('follower_count')->default(0);
            $table->bigInteger('following_count')->default(0);
            $table->bigInteger('likes_count')->default(0);
            $table->bigInteger('video_count')->default(0);
            $table->integer('follower_change')->nullable();
            $table->integer('following_change')->nullable();
            $table->integer('likes_change')->nullable();
            $table->integer('video_count_change')->nullable();
            $table->bigInteger('total_views_change')->nullable();
            $table->timestamps();

            $table->unique(['tiktok_account_id', 'date'], 'unique_tiktok_account_id_date');
        });

        // ── Videos ────────────────────────────────────────────
        Schema::create('tiktok_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tiktok_account_id')->constrained('tiktok_accounts')->cascadeOnDelete();
            $table->string('video_id', 100)->unique();
            $table->string('title', 500)->nullable();
            $table->text('video_description')->nullable();
            $table->text('cover_image_url')->nullable();
            $table->text('share_url')->nullable();
            $table->text('embed_link')->nullable();
            $table->integer('duration')->default(0);
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->bigInteger('view_count')->default(0);
            $table->bigInteger('like_count')->default(0);
            $table->bigInteger('comment_count')->default(0);
            $table->bigInteger('share_count')->default(0);
            $table->timestamp('created_at_tiktok')->nullable();
            $table->timestamps();

            $table->index(['tiktok_account_id', 'created_at_tiktok']);
        });

        // ── Video Snapshots (daily) ───────────────────────────
        Schema::create('tiktok_video_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tiktok_video_id')->constrained('tiktok_videos')->cascadeOnDelete();
            $table->date('date');
            $table->bigInteger('view_count')->default(0);
            $table->bigInteger('like_count')->default(0);
            $table->bigInteger('comment_count')->default(0);
            $table->bigInteger('share_count')->default(0);
            $table->integer('view_change')->nullable();
            $table->integer('like_change')->nullable();
            $table->integer('comment_change')->nullable();
            $table->integer('share_change')->nullable();
            $table->timestamps();

            $table->unique(['tiktok_video_id', 'date'], 'unique_tiktok_video_id_date');
        });

        // ── Campaigns (Ads) ───────────────────────────────────
        Schema::create('tiktok_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id', 40)->unique();
            $table->string('advertiser_id', 40);
            $table->string('name');
            $table->string('objective')->nullable();
            $table->string('status', 30)->default('ENABLE');
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('budget_mode', 20)->nullable();
            $table->timestamps();

            $table->index('advertiser_id', 'idx_tt_campaign_advertiser');
        });

        // ── Ads Insights (daily) ──────────────────────────────
        Schema::create('tiktok_ads_insights', function (Blueprint $table) {
            $table->id();
            $table->string('advertiser_id', 40);
            $table->unsignedBigInteger('tiktok_campaign_id')->nullable();
            $table->date('date');
            $table->decimal('spend', 12, 4)->default(0);
            $table->bigInteger('impressions')->default(0);
            $table->bigInteger('reach')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('video_views')->default(0);
            $table->integer('video_watched_2s')->default(0);
            $table->integer('video_watched_6s')->default(0);
            $table->integer('video_views_p25')->default(0);
            $table->integer('video_views_p50')->default(0);
            $table->integer('video_views_p75')->default(0);
            $table->integer('video_views_p100')->default(0);
            $table->integer('average_video_play')->default(0);
            $table->integer('likes')->default(0);
            $table->integer('comments')->default(0);
            $table->integer('shares')->default(0);
            $table->integer('follows')->default(0);
            $table->integer('profile_visits')->default(0);
            $table->integer('conversions')->default(0);
            $table->decimal('cost_per_conversion', 12, 4)->default(0);
            $table->integer('purchases')->default(0);
            $table->decimal('purchase_value', 12, 2)->default(0);
            $table->integer('add_to_cart')->default(0);
            $table->integer('initiate_checkout')->default(0);
            $table->integer('registrations')->default(0);
            $table->integer('landing_page_views')->default(0);
            $table->json('age_breakdown')->nullable();
            $table->json('gender_breakdown')->nullable();
            $table->json('platform_breakdown')->nullable();
            $table->timestamp('synced_at')->nullable();

            $table->foreign('tiktok_campaign_id')->references('id')->on('tiktok_campaigns')->nullOnDelete();
            $table->unique(['advertiser_id', 'date', 'tiktok_campaign_id'], 'unique_tt_adv_date_campaign');
            $table->index('date', 'idx_tt_insights_date');
            $table->index(['tiktok_campaign_id', 'date'], 'idx_tt_campaign_date');
        });

        // ── Sync Logs ─────────────────────────────────────────
        Schema::create('tiktok_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sync_type', 20);
            $table->string('data_type', 20);
            $table->string('status', 20)->default('started');
            $table->integer('records_synced')->default(0);
            $table->integer('records_failed')->default(0);
            $table->integer('api_calls_used')->default(0);
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['data_type', 'created_at'], 'idx_tt_data_type_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_sync_logs');
        Schema::dropIfExists('tiktok_ads_insights');
        Schema::dropIfExists('tiktok_campaigns');
        Schema::dropIfExists('tiktok_video_snapshots');
        Schema::dropIfExists('tiktok_videos');
        Schema::dropIfExists('tiktok_account_snapshots');
        Schema::dropIfExists('tiktok_accounts');
        Schema::dropIfExists('tiktok_tokens');
    }
};
