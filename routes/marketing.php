<?php

use App\Enums\MarketingPermissionEnum as P;
use App\Http\Controllers\Marketing\ContentPlannerApiController;
use App\Http\Controllers\Marketing\ContentPlannerController;
use App\Http\Controllers\Marketing\DailyBasketController;
use App\Http\Controllers\Marketing\MerchCalendarController;
use App\Http\Controllers\Marketing\InfluencerProductsController;
use App\Http\Controllers\Marketing\InfluencerReportsController;
use App\Http\Controllers\Marketing\InfluencersController;
use App\Http\Controllers\Marketing\MarketingDashboardController;
use App\Http\Controllers\Marketing\MetaAuthController;
use App\Http\Controllers\Marketing\MetaMarketingV2ChannelsController;
use App\Http\Controllers\Marketing\MetaMarketingV2Controller;
use App\Http\Controllers\Marketing\SocialInboxController;
use App\Http\Controllers\Marketing\TiktokAuthController;
use App\Http\Middleware\EnsureMarketingAccess;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketing Routes
|--------------------------------------------------------------------------
|
| All routes require authentication + marketing access.
| Granular permissions applied per section.
|
*/

Route::middleware(['auth', EnsureMarketingAccess::class])->group(function () {

    // ─── Dashboard ──────────────────────────────────
    Route::get('/', [MarketingDashboardController::class, 'index'])->name('dashboard');

    // ─── Content Planner ────────────────────────────
    Route::prefix('planner')->as('planner.')->middleware('marketing.permission:' . P::CONTENT_PLANNER_VIEW->value)->group(function () {
        // Page views
        Route::get('/', [ContentPlannerController::class, 'calendar'])->name('calendar');
        Route::get('/list', [ContentPlannerController::class, 'list'])->name('list');
        Route::get('/grid', [ContentPlannerController::class, 'grid'])->name('grid');
        Route::get('/media', [ContentPlannerController::class, 'media'])->name('media');

        // API: Posts
        Route::get('/api/posts/feed', [ContentPlannerApiController::class, 'feedPosts'])->name('api.posts.feed');
        Route::get('/api/posts', [ContentPlannerApiController::class, 'listPosts'])->name('api.posts.index');
        Route::get('/api/posts/paginated', [ContentPlannerApiController::class, 'listPostsPaginated'])->name('api.posts.paginated');
        Route::get('/api/posts/{id}', [ContentPlannerApiController::class, 'getPost'])->name('api.posts.show');

        Route::post('/api/posts', [ContentPlannerApiController::class, 'storePost'])
            ->name('api.posts.store')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_CREATE->value);
        Route::put('/api/posts/{id}', [ContentPlannerApiController::class, 'updatePost'])
            ->name('api.posts.update')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_EDIT->value);
        Route::delete('/api/posts/{id}', [ContentPlannerApiController::class, 'deletePost'])
            ->name('api.posts.destroy')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_DELETE->value);
        Route::patch('/api/posts/{id}/status', [ContentPlannerApiController::class, 'changeStatus'])
            ->name('api.posts.status')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_PUBLISH->value);
        Route::patch('/api/posts/{id}/schedule', [ContentPlannerApiController::class, 'reschedule'])
            ->name('api.posts.schedule')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_EDIT->value);
        Route::patch('/api/posts/reorder', [ContentPlannerApiController::class, 'reorderGrid'])
            ->name('api.posts.reorder')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_EDIT->value);
        Route::post('/api/posts/sync-meta', [ContentPlannerApiController::class, 'syncFromMeta'])
            ->name('api.posts.sync-meta')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_MANAGE->value);
        Route::post('/api/posts/{id}/duplicate', [ContentPlannerApiController::class, 'duplicatePost'])
            ->name('api.posts.duplicate')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_CREATE->value);
        Route::get('/api/schedule/suggestions', [ContentPlannerApiController::class, 'scheduleSuggestions'])
            ->name('api.schedule.suggestions');
        Route::post('/api/posts/batch-schedule', [ContentPlannerApiController::class, 'batchSchedule'])
            ->name('api.posts.batch-schedule')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_PUBLISH->value);

        // API: AI (requires edit permission — AI modifies content)
        Route::post('/api/ai/generate-caption', [ContentPlannerApiController::class, 'aiGenerateCaption'])
            ->name('api.ai.generate-caption')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_EDIT->value);
        Route::post('/api/ai/suggest-hashtags', [ContentPlannerApiController::class, 'aiSuggestHashtags'])
            ->name('api.ai.suggest-hashtags')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_EDIT->value);
        Route::post('/api/ai/rewrite', [ContentPlannerApiController::class, 'aiRewriteContent'])
            ->name('api.ai.rewrite')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_EDIT->value);

        // API: Media
        Route::post('/api/media/upload', [ContentPlannerApiController::class, 'uploadMedia'])
            ->name('api.media.upload')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_CREATE->value);
        Route::get('/api/media', [ContentPlannerApiController::class, 'listMedia'])->name('api.media.index');
        Route::delete('/api/media/{id}', [ContentPlannerApiController::class, 'deleteMedia'])
            ->name('api.media.destroy')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_DELETE->value);

        // API: Comments (edit permission for create/resolve, delete for remove)
        Route::post('/api/comments', [ContentPlannerApiController::class, 'storeComment'])
            ->name('api.comments.store')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_EDIT->value);
        Route::delete('/api/comments/{id}', [ContentPlannerApiController::class, 'deleteComment'])
            ->name('api.comments.destroy')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_DELETE->value);
        Route::patch('/api/comments/{id}/resolve', [ContentPlannerApiController::class, 'resolveComment'])
            ->name('api.comments.resolve')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_EDIT->value);

        // API: Labels (manage permission for CUD)
        Route::get('/api/labels', [ContentPlannerApiController::class, 'listLabels'])->name('api.labels.index');
        Route::post('/api/labels', [ContentPlannerApiController::class, 'storeLabel'])
            ->name('api.labels.store')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_MANAGE->value);
        Route::put('/api/labels/{id}', [ContentPlannerApiController::class, 'updateLabel'])
            ->name('api.labels.update')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_MANAGE->value);
        Route::delete('/api/labels/{id}', [ContentPlannerApiController::class, 'deleteLabel'])
            ->name('api.labels.destroy')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_MANAGE->value);

        // API: Campaigns (manage permission for CUD)
        Route::get('/api/campaigns', [ContentPlannerApiController::class, 'listCampaigns'])->name('api.campaigns.index');
        Route::post('/api/campaigns', [ContentPlannerApiController::class, 'storeCampaign'])
            ->name('api.campaigns.store')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_MANAGE->value);
        Route::put('/api/campaigns/{id}', [ContentPlannerApiController::class, 'updateCampaign'])
            ->name('api.campaigns.update')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_MANAGE->value);
        Route::delete('/api/campaigns/{id}', [ContentPlannerApiController::class, 'deleteCampaign'])
            ->name('api.campaigns.destroy')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_MANAGE->value);

        // API: Approval Steps (approve permission for CUD)
        Route::get('/api/posts/{postId}/approval-steps', [ContentPlannerApiController::class, 'listApprovalSteps'])->name('api.approval-steps.index');
        Route::post('/api/posts/{postId}/approval-steps', [ContentPlannerApiController::class, 'storeApprovalStep'])
            ->name('api.approval-steps.store')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_APPROVE->value);
        Route::patch('/api/approval-steps/{stepId}/act', [ContentPlannerApiController::class, 'actOnApprovalStep'])
            ->name('api.approval-steps.act')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_APPROVE->value);
        Route::delete('/api/approval-steps/{stepId}', [ContentPlannerApiController::class, 'deleteApprovalStep'])
            ->name('api.approval-steps.destroy')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_APPROVE->value);

        // API: Versions (edit permission to restore)
        Route::get('/api/posts/{postId}/versions', [ContentPlannerApiController::class, 'listVersions'])->name('api.versions.index');
        Route::post('/api/versions/{versionId}/restore', [ContentPlannerApiController::class, 'restoreVersion'])
            ->name('api.versions.restore')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_EDIT->value);

        // API: Suggestions (edit permission)
        Route::get('/api/posts/{postId}/suggestions', [ContentPlannerApiController::class, 'listSuggestions'])->name('api.suggestions.index');
        Route::post('/api/posts/{postId}/suggestions', [ContentPlannerApiController::class, 'storeSuggestion'])
            ->name('api.suggestions.store')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_EDIT->value);
        Route::patch('/api/suggestions/{suggestionId}/resolve', [ContentPlannerApiController::class, 'resolveSuggestion'])
            ->name('api.suggestions.resolve')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_EDIT->value);

        // API: Share Links (manage permission)
        Route::get('/api/posts/{postId}/share-links', [ContentPlannerApiController::class, 'listShareLinks'])->name('api.share-links.index');
        Route::post('/api/posts/{postId}/share-links', [ContentPlannerApiController::class, 'createShareLink'])
            ->name('api.share-links.store')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_MANAGE->value);
        Route::delete('/api/share-links/{linkId}', [ContentPlannerApiController::class, 'deactivateShareLink'])
            ->name('api.share-links.destroy')
            ->middleware('marketing.permission:' . P::CONTENT_PLANNER_MANAGE->value);

        // Social Inbox
        Route::get('/inbox', [SocialInboxController::class, 'index'])->name('inbox');
        Route::get('/api/inbox/comments', [SocialInboxController::class, 'fetchComments'])->name('api.inbox.comments');
        Route::post('/api/inbox/sync', [SocialInboxController::class, 'syncPlatformComments'])->name('api.inbox.sync');
        Route::post('/api/inbox/comments/{comment}/reply', [SocialInboxController::class, 'replyToComment'])->name('api.inbox.reply');
    });

    // ─── Merch Calendar ────────────────────────────
    Route::prefix('merch-calendar')->as('merch-calendar.')->group(function () {
        // Page views
        Route::get('/', [MerchCalendarController::class, 'calendar'])->name('calendar');
        Route::get('/timeline', [MerchCalendarController::class, 'timeline'])->name('timeline');
        Route::get('/gantt', [MerchCalendarController::class, 'gantt'])->name('gantt');
        Route::get('/quick-scan', [MerchCalendarController::class, 'quickScan'])->name('quick-scan');

        // API proxies
        Route::get('/api/weeks', [MerchCalendarController::class, 'weeksJson'])->name('api.weeks');
        Route::get('/api/weeks-summary', [MerchCalendarController::class, 'weeksSummaryJson'])->name('api.weeks.summary');
        Route::get('/api/weeks/{id}', [MerchCalendarController::class, 'weekDetail'])->name('api.weeks.show');
        Route::post('/api/weeks', [MerchCalendarController::class, 'storeWeek'])->name('api.weeks.store');
        Route::put('/api/weeks/{id}', [MerchCalendarController::class, 'updateWeek'])->name('api.weeks.update');
        Route::delete('/api/weeks/{id}', [MerchCalendarController::class, 'deleteWeek'])->name('api.weeks.destroy');
        Route::post('/api/weeks/{id}/status', [MerchCalendarController::class, 'updateStatus'])->name('api.weeks.status');
        Route::get('/api/item-groups/search', [MerchCalendarController::class, 'searchGroups'])->name('api.item-groups.search');
        Route::get('/api/price-lists', [MerchCalendarController::class, 'priceLists'])->name('api.price-lists');

        // Per-product day assignment (UI inline picker — proxy te DIS internal API)
        Route::post('/api/weeks/{week}/groups/{group}/dates', [MerchCalendarController::class, 'assignGroupDate'])
            ->name('api.weeks.groups.dates.store');
        Route::delete('/api/weeks/{week}/groups/{group}/dates/{dateId}', [MerchCalendarController::class, 'removeGroupDate'])
            ->name('api.weeks.groups.dates.destroy');

        // Quick Scan bulk save — proxy ne DIS internal API
        Route::post('/api/weeks/{week}/quick-scan', [MerchCalendarController::class, 'quickScanSave'])
            ->name('api.weeks.quick-scan');

        // Barcode lookup proxy (per Quick Scan UI)
        Route::get('/api/items/by-barcode', [MerchCalendarController::class, 'lookupBarcode'])
            ->name('api.items.by-barcode');
    });

    // ─── Shporta Ditore ─────────────────────────────
    Route::prefix('daily-basket')->as('daily-basket.')->group(function () {
        // Page view
        Route::get('/', [DailyBasketController::class, 'index'])->name('index');

        // Collection picker + summary + one day's basket
        Route::get('/api/collections', [DailyBasketController::class, 'listCollections'])
            ->name('api.collections.index');
        Route::get('/api/collections/{distributionWeek}', [DailyBasketController::class, 'collectionSummary'])
            ->name('api.collections.summary');
        Route::get('/api/collections/{distributionWeek}/{date}', [DailyBasketController::class, 'show'])
            ->name('api.day.show');

        // Post CRUD + transitions
        Route::post('/api/baskets/{basketId}/posts', [DailyBasketController::class, 'storePost'])
            ->name('api.posts.store');
        Route::put('/api/posts/{post}', [DailyBasketController::class, 'updatePost'])
            ->name('api.posts.update');
        Route::put('/api/posts/{post}/products', [DailyBasketController::class, 'syncProducts'])
            ->name('api.posts.products');
        Route::post('/api/posts/{post}/transition', [DailyBasketController::class, 'transitionPost'])
            ->name('api.posts.transition');
        Route::delete('/api/posts/{post}', [DailyBasketController::class, 'deletePost'])
            ->name('api.posts.destroy');
    });

    // ─── CDN Image Proxy (bypasses hotlink protection) ──
    Route::get('/cdn-image', function (\Illuminate\Http\Request $request) {
        $url = $request->query('url');
        if (!$url || !str_starts_with($url, 'https://web-cdn.zeroabsolute.com/')) {
            abort(400);
        }
        $response = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(10)->get($url);
        if ($response->failed()) {
            abort(404);
        }
        return response($response->body(), 200)
            ->header('Content-Type', $response->header('Content-Type') ?: 'image/jpeg')
            ->header('Cache-Control', 'public, max-age=86400');
    })->name('cdn-image');

    // ─── Analytics ──────────────────────────────────
    Route::prefix('analytics')->as('analytics.')->middleware('marketing.permission:' . P::ANALYTICS_VIEW->value)->group(function () {
        Route::get('/', [MetaMarketingV2Controller::class, 'index'])->name('index');
        Route::get('/ads', [MetaMarketingV2ChannelsController::class, 'adsReport'])->name('ads');
        Route::get('/instagram', [MetaMarketingV2ChannelsController::class, 'instagramReport'])->name('instagram');
        Route::get('/facebook', [MetaMarketingV2ChannelsController::class, 'facebookReport'])->name('facebook');
        Route::get('/tiktok', [MetaMarketingV2ChannelsController::class, 'tiktokReport'])->name('tiktok');

        Route::get('/api/sync-status', [MetaMarketingV2Controller::class, 'syncStatus'])->name('api.sync-status');
        Route::get('/api/sync', [MetaMarketingV2Controller::class, 'syncData'])
            ->name('api.sync')
            ->middleware('marketing.permission:' . P::ANALYTICS_MANAGE->value);

        // Total KPIs
        Route::get('/api/total-kpis', [MetaMarketingV2Controller::class, 'totalKpis'])->name('api.total-kpis');
        Route::get('/api/total-daily', [MetaMarketingV2Controller::class, 'totalDaily'])->name('api.total-daily');
        Route::get('/api/total-comparison', [MetaMarketingV2Controller::class, 'totalComparison'])->name('api.total-comparison');

        // Ads
        Route::get('/api/ads-kpis', [MetaMarketingV2ChannelsController::class, 'adsKpis'])->name('api.ads-kpis');
        Route::get('/api/ads-daily', [MetaMarketingV2ChannelsController::class, 'adsDaily'])->name('api.ads-daily');
        Route::get('/api/ads-campaigns', [MetaMarketingV2ChannelsController::class, 'adsCampaigns'])->name('api.ads-campaigns');
        Route::get('/api/ads-breakdowns', [MetaMarketingV2ChannelsController::class, 'adsBreakdowns'])->name('api.ads-breakdowns');

        // Instagram
        Route::get('/api/ig-kpis', [MetaMarketingV2ChannelsController::class, 'igKpis'])->name('api.ig-kpis');
        Route::get('/api/ig-daily', [MetaMarketingV2ChannelsController::class, 'igDaily'])->name('api.ig-daily');
        Route::get('/api/ig-top-posts', [MetaMarketingV2ChannelsController::class, 'igTopPosts'])->name('api.ig-top-posts');
        Route::get('/api/ig-messaging', [MetaMarketingV2ChannelsController::class, 'igMessaging'])->name('api.ig-messaging');

        // Facebook
        Route::get('/api/fb-kpis', [MetaMarketingV2ChannelsController::class, 'fbKpis'])->name('api.fb-kpis');
        Route::get('/api/fb-daily', [MetaMarketingV2ChannelsController::class, 'fbDaily'])->name('api.fb-daily');
        Route::get('/api/fb-top-posts', [MetaMarketingV2ChannelsController::class, 'fbTopPosts'])->name('api.fb-top-posts');
        Route::get('/api/fb-messaging', [MetaMarketingV2ChannelsController::class, 'fbMessaging'])->name('api.fb-messaging');

        // TikTok
        Route::get('/api/tiktok-kpis', [MetaMarketingV2ChannelsController::class, 'tiktokKpis'])->name('api.tiktok-kpis');
        Route::get('/api/tiktok-daily', [MetaMarketingV2ChannelsController::class, 'tiktokDaily'])->name('api.tiktok-daily');
        Route::get('/api/tiktok-campaigns', [MetaMarketingV2ChannelsController::class, 'tiktokCampaigns'])->name('api.tiktok-campaigns');
        Route::get('/api/tiktok-breakdowns', [MetaMarketingV2ChannelsController::class, 'tiktokBreakdowns'])->name('api.tiktok-breakdowns');
        Route::get('/api/tiktok-top-videos', [MetaMarketingV2ChannelsController::class, 'tiktokTopVideos'])->name('api.tiktok-top-videos');
    });

    // ─── Meta & TikTok Auth ─────────────────────────
    Route::prefix('meta-auth')->as('meta-auth.')->middleware('marketing.permission:' . P::ANALYTICS_MANAGE->value)->group(function () {
        Route::get('/', [MetaAuthController::class, 'index'])->name('index');
        Route::post('/save-token', [MetaAuthController::class, 'saveToken'])->name('save-token');
        Route::delete('/tokens/{token}', [MetaAuthController::class, 'deleteToken'])->name('delete-token');
        Route::post('/test-token', [MetaAuthController::class, 'testToken'])->name('test-token');
    });

    Route::prefix('tiktok-auth')->as('tiktok-auth.')->middleware('marketing.permission:' . P::ANALYTICS_MANAGE->value)->group(function () {
        Route::get('/', [TiktokAuthController::class, 'index'])->name('index');
        Route::get('/redirect', [TiktokAuthController::class, 'redirect'])->name('redirect');
        Route::get('/callback', [TiktokAuthController::class, 'callback'])->name('callback');
        Route::delete('/tokens/{token}', [TiktokAuthController::class, 'deleteToken'])->name('delete-token');
    });

    // ─── Influencers ────────────────────────────────
    Route::prefix('influencers')->as('influencers.')->group(function () {
        Route::get('/', [InfluencersController::class, 'index'])
            ->name('index')
            ->middleware('marketing.permission:' . P::INFLUENCER_VIEW_ANY->value);
        Route::post('/', [InfluencersController::class, 'store'])
            ->name('store')
            ->middleware('marketing.permission:' . P::INFLUENCER_CREATE->value);
        Route::get('/search', [InfluencersController::class, 'search'])
            ->name('search')
            ->middleware('marketing.permission:' . P::INFLUENCER_VIEW_ANY->value);
        Route::get('/{influencer}', [InfluencersController::class, 'show'])
            ->name('show')
            ->middleware('marketing.permission:' . P::INFLUENCER_VIEW->value);
        Route::put('/{influencer}', [InfluencersController::class, 'update'])
            ->name('update')
            ->middleware('marketing.permission:' . P::INFLUENCER_UPDATE->value);
    });

    // ─── Influencer Products ────────────────────────
    Route::prefix('influencer-products')->as('influencer-products.')->group(function () {
        Route::get('/', [InfluencerProductsController::class, 'index'])
            ->name('index')
            ->middleware('marketing.permission:' . P::INFLUENCER_PRODUCT_VIEW_ANY->value);
        Route::get('/create', [InfluencerProductsController::class, 'create'])
            ->name('create')
            ->middleware('marketing.permission:' . P::INFLUENCER_PRODUCT_CREATE->value);
        Route::get('/search-items', [InfluencerProductsController::class, 'searchItems'])
            ->name('search-items')
            ->middleware('marketing.permission:' . P::INFLUENCER_PRODUCT_CREATE->value);
        Route::get('/warehouses-for-branch', [InfluencerProductsController::class, 'getWarehousesForBranch'])
            ->name('warehouses-for-branch')
            ->middleware('marketing.permission:' . P::INFLUENCER_PRODUCT_CREATE->value);
        Route::post('/', [InfluencerProductsController::class, 'store'])
            ->name('store')
            ->middleware('marketing.permission:' . P::INFLUENCER_PRODUCT_CREATE->value);
        Route::get('/{influencer_product}', [InfluencerProductsController::class, 'show'])
            ->name('show')
            ->middleware('marketing.permission:' . P::INFLUENCER_PRODUCT_VIEW->value);
        Route::post('/{influencer_product}/activate', [InfluencerProductsController::class, 'activate'])
            ->name('activate')
            ->middleware('marketing.permission:' . P::INFLUENCER_PRODUCT_ACTIVATE->value);
        Route::post('/{influencer_product}/return', [InfluencerProductsController::class, 'registerReturn'])
            ->name('return')
            ->middleware('marketing.permission:' . P::INFLUENCER_PRODUCT_RETURN->value);
        Route::post('/{influencer_product}/convert', [InfluencerProductsController::class, 'convertToExpense'])
            ->name('convert')
            ->middleware('marketing.permission:' . P::INFLUENCER_PRODUCT_CONVERT->value);
        Route::post('/{influencer_product}/cancel', [InfluencerProductsController::class, 'cancel'])
            ->name('cancel')
            ->middleware('marketing.permission:' . P::INFLUENCER_PRODUCT_CANCEL->value);
        Route::post('/{influencer_product}/extend', [InfluencerProductsController::class, 'extend'])
            ->name('extend')
            ->middleware('marketing.permission:' . P::INFLUENCER_PRODUCT_RETURN->value);
    });

    // ─── Influencer Reports ─────────────────────────
    Route::prefix('influencer-reports')->as('influencer-reports.')->middleware('marketing.permission:' . P::INFLUENCER_VIEW_ANY->value)->group(function () {
        Route::get('/dashboard', [InfluencerReportsController::class, 'dashboard'])->name('dashboard');
        Route::get('/overdue', [InfluencerReportsController::class, 'overdueProducts'])->name('overdue');
        Route::get('/value-by-influencer', [InfluencerReportsController::class, 'valueByInfluencer'])->name('value-by-influencer');
        Route::get('/monthly', [InfluencerReportsController::class, 'monthlyActivity'])->name('monthly');
        Route::get('/chart-data', [InfluencerReportsController::class, 'getChartData'])->name('chart-data');
    });
});
