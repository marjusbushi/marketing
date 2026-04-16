<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated Meta (Facebook + Instagram) analytics schema.
 *
 * All tables are self-contained — no user FK references.
 * Tokens, ad accounts, campaigns, ad sets, insights, page/IG metrics.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Tokens ────────────────────────────────────────────
        Schema::create('meta_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('token_type', 30); // system_user, long_lived_user, page
            $table->text('access_token');
            $table->string('meta_user_id', 50)->nullable();
            $table->string('page_id', 50)->nullable();
            $table->string('ig_account_id', 50)->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('token_type');
            $table->index('is_active');
        });

        // ── Ad Accounts ───────────────────────────────────────
        Schema::create('meta_ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_id', 50)->unique();
            $table->string('name', 255);
            $table->string('currency', 10)->default('EUR');
            $table->string('timezone', 50)->default('Europe/Tirane');
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamps();
        });

        // ── Campaigns ─────────────────────────────────────────
        Schema::create('meta_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_ad_account_id')->constrained('meta_ad_accounts')->cascadeOnDelete();
            $table->string('campaign_id', 50)->unique();
            $table->string('name', 255);
            $table->string('objective', 50)->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->decimal('daily_budget', 12, 4)->nullable();
            $table->decimal('lifetime_budget', 12, 4)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index('meta_ad_account_id');
        });

        // ── Ad Sets ───────────────────────────────────────────
        Schema::create('meta_ad_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_campaign_id')->constrained('meta_campaigns')->cascadeOnDelete();
            $table->string('adset_id', 50)->unique();
            $table->string('name', 255);
            $table->string('status', 20)->default('ACTIVE');
            $table->decimal('daily_budget', 12, 4)->nullable();
            $table->json('targeting_summary')->nullable();
            $table->string('optimization_goal', 50)->nullable();
            $table->timestamps();

            $table->index('meta_campaign_id');
        });

        // ── Ads Insights (daily per ad set) ───────────────────
        Schema::create('meta_ads_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_ad_account_id')->constrained('meta_ad_accounts')->cascadeOnDelete();
            $table->foreignId('meta_campaign_id')->constrained('meta_campaigns')->cascadeOnDelete();
            $table->foreignId('meta_ad_set_id')->constrained('meta_ad_sets')->cascadeOnDelete();
            $table->date('date');
            $table->bigInteger('impressions')->default(0);
            $table->bigInteger('reach')->default(0);
            $table->integer('clicks')->default(0);
            $table->decimal('spend', 12, 4)->default(0);
            $table->integer('post_engagement')->default(0);
            $table->integer('page_engagement')->default(0);
            $table->integer('link_clicks')->default(0);
            $table->integer('video_views')->default(0);
            $table->integer('purchases')->default(0);
            $table->decimal('purchase_value', 12, 2)->default(0);
            $table->integer('add_to_cart')->default(0);
            $table->integer('initiate_checkout')->default(0);
            $table->integer('leads')->default(0);
            $table->unsignedInteger('messaging_conversations')->default(0);
            $table->unsignedInteger('messaging_conversations_replied')->default(0);
            $table->json('age_breakdown')->nullable();
            $table->json('gender_breakdown')->nullable();
            $table->json('platform_breakdown')->nullable();
            $table->json('placement_breakdown')->nullable();
            $table->timestamp('synced_at')->nullable();

            $table->unique(['meta_ad_set_id', 'date'], 'unique_adset_date');
            $table->index('date', 'idx_date');
            $table->index(['meta_campaign_id', 'date'], 'idx_campaign_date');
            $table->index(['meta_ad_account_id', 'date'], 'idx_account_date');
        });

        // ── Ads Period Reach ──────────────────────────────────
        Schema::create('meta_ads_period_reach', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_ad_account_id')->constrained('meta_ad_accounts')->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->bigInteger('reach')->default(0);
            $table->timestamp('synced_at')->nullable();

            $table->unique(['meta_ad_account_id', 'date_from', 'date_to'], 'unique_period_reach');
        });

        // ── Page Insights (Facebook) ──────────────────────────
        Schema::create('meta_page_insights', function (Blueprint $table) {
            $table->id();
            $table->string('page_id', 50);
            $table->date('date');
            $table->integer('page_impressions')->default(0);
            $table->integer('page_impressions_organic')->default(0);
            $table->integer('page_impressions_paid')->default(0);
            $table->integer('page_reach')->default(0);
            $table->integer('page_views_total')->default(0);
            $table->integer('page_post_engagements')->default(0);
            $table->unsignedInteger('page_fans')->default(0);
            $table->unsignedInteger('page_followers')->default(0);
            $table->unsignedBigInteger('page_posts_impressions')->default(0);
            $table->unsignedBigInteger('page_messages_new_threads')->default(0);
            $table->integer('page_video_views')->default(0);
            $table->integer('page_daily_follows')->default(0);
            $table->integer('page_daily_unfollows')->default(0);
            $table->integer('page_posts_impressions_paid')->default(0);
            $table->integer('page_posts_impressions_organic')->default(0);
            $table->unsignedInteger('page_reactions_total')->default(0);
            $table->unsignedInteger('page_reels_views')->default(0);
            $table->timestamp('synced_at')->nullable();

            $table->unique(['page_id', 'date'], 'unique_page_date');
        });

        // ── Instagram Insights ────────────────────────────────
        Schema::create('meta_ig_insights', function (Blueprint $table) {
            $table->id();
            $table->string('ig_account_id', 50);
            $table->date('date');
            $table->integer('impressions')->default(0);
            $table->integer('reach')->default(0);
            $table->integer('profile_views')->default(0);
            $table->integer('follower_count')->default(0);
            $table->integer('new_followers')->default(0);
            $table->integer('website_clicks')->default(0);
            $table->integer('views')->default(0);
            $table->integer('accounts_engaged')->default(0);
            $table->integer('total_interactions')->default(0);
            $table->integer('likes')->default(0);
            $table->integer('comments')->default(0);
            $table->integer('shares')->default(0);
            $table->integer('saves')->default(0);
            $table->integer('replies')->default(0);
            $table->timestamp('synced_at')->nullable();

            $table->unique(['ig_account_id', 'date'], 'unique_ig_date');
        });

        // ── Post Insights (FB + IG) ──────────────────────────
        Schema::create('meta_post_insights', function (Blueprint $table) {
            $table->id();
            $table->string('source', 10); // facebook, instagram
            $table->string('source_id', 50);
            $table->string('post_id', 50)->unique();
            $table->string('post_type', 20);
            $table->text('message')->nullable();
            $table->string('permalink_url', 500)->nullable();
            $table->string('media_url', 500)->nullable();
            $table->timestamp('created_at_meta')->nullable();
            $table->integer('impressions')->default(0);
            $table->integer('reach')->default(0);
            $table->integer('likes')->default(0);
            $table->integer('comments')->default(0);
            $table->integer('shares')->default(0);
            $table->integer('saves')->default(0);
            $table->integer('video_views')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('exits')->default(0)->nullable();
            $table->integer('replies')->default(0)->nullable();
            $table->integer('taps_forward')->default(0)->nullable();
            $table->integer('taps_back')->default(0)->nullable();
            $table->integer('plays')->default(0)->nullable();
            $table->timestamp('synced_at')->nullable();

            $table->index(['source', 'created_at_meta'], 'idx_source_created');
            $table->index('post_type', 'idx_post_type');
            $table->index('created_at_meta', 'idx_created_at_meta');
        });

        // ── Messaging Stats ───────────────────────────────────
        Schema::create('meta_messaging_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('platform', 20); // messenger, instagram
            $table->integer('new_conversations')->default(0);
            $table->integer('total_messages_received')->default(0);
            $table->integer('total_messages_sent')->default(0);
            $table->timestamp('synced_at')->nullable();

            $table->unique(['date', 'platform'], 'unique_date_platform');
        });

        // ── Period Totals ─────────────────────────────────────
        Schema::create('meta_period_totals', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20); // facebook, instagram, ads
            $table->date('date_from');
            $table->date('date_to');
            $table->json('metrics');
            $table->timestamp('synced_at')->nullable();

            $table->unique(['platform', 'date_from', 'date_to'], 'unique_period_totals');
            $table->index(['date_from', 'date_to'], 'idx_period_range');
        });

        // ── Sync Logs ─────────────────────────────────────────
        Schema::create('meta_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sync_type', 20); // full, daily, manual
            $table->string('data_type', 20); // ads, page, ig, posts, messaging
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('status', 20)->default('started');
            $table->integer('records_synced')->default(0);
            $table->integer('records_failed')->default(0);
            $table->integer('api_calls_used')->default(0);
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamps();

            $table->index('status', 'idx_status');
            $table->index(['data_type', 'created_at'], 'idx_data_type_date');
        });

        // ── Raw API Events ────────────────────────────────────
        Schema::create('meta_raw_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id')->index();
            $table->string('endpoint', 500);
            $table->string('method', 10)->default('GET');
            $table->string('token_type', 20)->default('system_user');
            $table->json('request_params')->nullable();
            $table->longText('response_body')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('is_error')->default(false);
            $table->string('error_message', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index('is_error');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_raw_events');
        Schema::dropIfExists('meta_sync_logs');
        Schema::dropIfExists('meta_period_totals');
        Schema::dropIfExists('meta_messaging_stats');
        Schema::dropIfExists('meta_post_insights');
        Schema::dropIfExists('meta_ig_insights');
        Schema::dropIfExists('meta_page_insights');
        Schema::dropIfExists('meta_ads_period_reach');
        Schema::dropIfExists('meta_ads_insights');
        Schema::dropIfExists('meta_ad_sets');
        Schema::dropIfExists('meta_campaigns');
        Schema::dropIfExists('meta_ad_accounts');
        Schema::dropIfExists('meta_tokens');
    }
};
