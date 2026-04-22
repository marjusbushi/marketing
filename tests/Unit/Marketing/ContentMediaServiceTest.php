<?php

namespace Tests\Unit\Marketing;

use App\Models\Content\ContentMedia;
use App\Services\ContentPlanner\ContentMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Media Library v2 — Service-level tests.
 *
 * Covers:
 *   • classifyFolder() — mime + aspect routing (video portrait/landscape, image, unknown)
 *   • setStage() / setFolder() — validation rules
 *   • bulkMove() / bulkSetStage() — batch update semantics
 *   • folderCounts() — shape + null-bucket handling
 *   • list() — folder + stage filters (incl. __uncategorized, __all)
 */
class ContentMediaServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContentMediaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContentMediaService();
    }

    // ── classifyFolder ──

    public function test_classify_folder_returns_reels_for_portrait_video(): void
    {
        $this->assertSame('reels', $this->service->classifyFolder('video/mp4', 1080, 1920));
    }

    public function test_classify_folder_returns_videos_for_landscape_video(): void
    {
        $this->assertSame('videos', $this->service->classifyFolder('video/mp4', 1920, 1080));
    }

    public function test_classify_folder_returns_videos_for_square_video(): void
    {
        // Square falls to 'videos' (not portrait).
        $this->assertSame('videos', $this->service->classifyFolder('video/mp4', 1080, 1080));
    }

    public function test_classify_folder_returns_photos_for_image(): void
    {
        $this->assertSame('photos', $this->service->classifyFolder('image/jpeg', 2000, 1500));
        $this->assertSame('photos', $this->service->classifyFolder('image/png', null, null));
    }

    public function test_classify_folder_returns_null_for_unknown_or_audio(): void
    {
        $this->assertNull($this->service->classifyFolder(null, 100, 100));
        $this->assertNull($this->service->classifyFolder('audio/mpeg', null, null));
        $this->assertNull($this->service->classifyFolder('application/pdf', 0, 0));
    }

    public function test_classify_folder_defaults_video_without_dimensions_to_videos(): void
    {
        $this->assertSame('videos', $this->service->classifyFolder('video/mp4', null, null));
    }

    // ── setStage / setFolder validation ──

    public function test_set_stage_rejects_invalid_value(): void
    {
        $media = ContentMedia::create($this->mediaAttrs());
        $this->expectException(\InvalidArgumentException::class);
        $this->service->setStage($media, 'wip');
    }

    public function test_set_stage_accepts_valid_values(): void
    {
        $media = ContentMedia::create($this->mediaAttrs());
        $this->service->setStage($media, 'edited');
        $this->assertSame('edited', $media->fresh()->stage);
        $this->service->setStage($media, 'final');
        $this->assertSame('final', $media->fresh()->stage);
    }

    public function test_set_folder_rejects_unknown_folder(): void
    {
        $media = ContentMedia::create($this->mediaAttrs());
        $this->expectException(\InvalidArgumentException::class);
        $this->service->setFolder($media, 'trash');
    }

    public function test_set_folder_accepts_null_and_valid_folders(): void
    {
        $media = ContentMedia::create($this->mediaAttrs());
        $this->service->setFolder($media, 'reels');
        $this->assertSame('reels', $media->fresh()->folder);
        $this->service->setFolder($media, null);
        $this->assertNull($media->fresh()->folder);
    }

    // ── Bulk ops ──

    public function test_bulk_move_updates_all_requested(): void
    {
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = ContentMedia::create($this->mediaAttrs())->id;
        }
        $affected = $this->service->bulkMove($ids, 'photos');
        $this->assertSame(3, $affected);
        foreach ($ids as $id) {
            $this->assertSame('photos', ContentMedia::find($id)->folder);
        }
    }

    public function test_bulk_set_stage_updates_all(): void
    {
        $ids = [
            ContentMedia::create($this->mediaAttrs())->id,
            ContentMedia::create($this->mediaAttrs())->id,
        ];
        $this->service->bulkSetStage($ids, 'final');
        foreach ($ids as $id) {
            $this->assertSame('final', ContentMedia::find($id)->stage);
        }
    }

    public function test_bulk_move_rejects_invalid_folder(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->bulkMove([1, 2, 3], 'archive');
    }

    // ── folderCounts ──

    public function test_folder_counts_includes_all_buckets_and_uncategorized(): void
    {
        ContentMedia::create($this->mediaAttrs(['folder' => 'reels']));
        ContentMedia::create($this->mediaAttrs(['folder' => 'reels']));
        ContentMedia::create($this->mediaAttrs(['folder' => 'photos']));
        ContentMedia::create($this->mediaAttrs(['folder' => null]));

        $counts = $this->service->folderCounts();
        $this->assertSame(2, $counts['reels']);
        $this->assertSame(1, $counts['photos']);
        $this->assertSame(0, $counts['videos']);
        $this->assertSame(0, $counts['stories']);
        $this->assertSame(0, $counts['referenca']);
        $this->assertSame(0, $counts['imported']);
        $this->assertSame(1, $counts['__uncategorized']);
        $this->assertSame(4, $counts['__all']);
    }

    // ── list() filters ──

    public function test_list_filters_by_folder(): void
    {
        ContentMedia::create($this->mediaAttrs(['folder' => 'reels']));
        ContentMedia::create($this->mediaAttrs(['folder' => 'reels']));
        ContentMedia::create($this->mediaAttrs(['folder' => 'photos']));

        $reels = $this->service->list(['folder' => 'reels']);
        $this->assertSame(2, $reels->total());

        $photos = $this->service->list(['folder' => 'photos']);
        $this->assertSame(1, $photos->total());
    }

    public function test_list_filters_uncategorized_means_folder_null(): void
    {
        ContentMedia::create($this->mediaAttrs(['folder' => 'reels']));
        ContentMedia::create($this->mediaAttrs(['folder' => null]));
        ContentMedia::create($this->mediaAttrs(['folder' => null]));

        $uncat = $this->service->list(['folder' => '__uncategorized']);
        $this->assertSame(2, $uncat->total());
    }

    public function test_list_filters_by_stage(): void
    {
        ContentMedia::create($this->mediaAttrs(['stage' => 'raw']));
        ContentMedia::create($this->mediaAttrs(['stage' => 'edited']));
        ContentMedia::create($this->mediaAttrs(['stage' => 'final']));
        ContentMedia::create($this->mediaAttrs(['stage' => 'final']));

        $this->assertSame(1, $this->service->list(['stage' => 'raw'])->total());
        $this->assertSame(1, $this->service->list(['stage' => 'edited'])->total());
        $this->assertSame(2, $this->service->list(['stage' => 'final'])->total());
    }

    public function test_list_combines_folder_and_stage_filters_as_and(): void
    {
        ContentMedia::create($this->mediaAttrs(['folder' => 'reels', 'stage' => 'raw']));
        ContentMedia::create($this->mediaAttrs(['folder' => 'reels', 'stage' => 'final']));
        ContentMedia::create($this->mediaAttrs(['folder' => 'photos', 'stage' => 'final']));

        $result = $this->service->list(['folder' => 'reels', 'stage' => 'final']);
        $this->assertSame(1, $result->total());
    }

    // ── Products + Collections linking (Media Library v3) ──

    public function test_link_products_inserts_new_and_ignores_duplicates(): void
    {
        $m = ContentMedia::create($this->mediaAttrs());
        $this->assertSame(2, $this->service->linkProducts($m, [101, 202]));
        // Re-linking same ids — insertOrIgnore returns 0 or 1 depending on driver;
        // key assertion is that the pivot still has exactly 2 rows.
        $this->service->linkProducts($m, [101, 303]);
        $this->assertEqualsCanonicalizing([101, 202, 303], $m->fresh()->item_group_ids);
    }

    public function test_link_products_with_replace_wipes_previous(): void
    {
        $m = ContentMedia::create($this->mediaAttrs());
        $this->service->linkProducts($m, [101, 202]);
        $this->service->linkProducts($m, [999], true);
        $this->assertSame([999], $m->fresh()->item_group_ids);
    }

    public function test_unlink_products_removes_only_specified(): void
    {
        $m = ContentMedia::create($this->mediaAttrs());
        $this->service->linkProducts($m, [1, 2, 3]);
        $this->service->unlinkProducts($m, [2]);
        $this->assertEqualsCanonicalizing([1, 3], $m->fresh()->item_group_ids);
    }

    public function test_bulk_link_products_creates_cartesian(): void
    {
        $a = ContentMedia::create($this->mediaAttrs())->id;
        $b = ContentMedia::create($this->mediaAttrs())->id;
        $inserted = $this->service->bulkLinkProducts([$a, $b], [10, 20]);
        $this->assertSame(4, $inserted);
        $this->assertEqualsCanonicalizing([10, 20], ContentMedia::find($a)->item_group_ids);
        $this->assertEqualsCanonicalizing([10, 20], ContentMedia::find($b)->item_group_ids);
    }

    public function test_link_collections_insert_and_replace(): void
    {
        $m = ContentMedia::create($this->mediaAttrs());
        $this->service->linkCollections($m, [5, 6]);
        $this->assertEqualsCanonicalizing([5, 6], $m->fresh()->distribution_week_ids);
        $this->service->linkCollections($m, [7], true);
        $this->assertSame([7], $m->fresh()->distribution_week_ids);
    }

    public function test_product_counts_returns_top_sorted_desc(): void
    {
        $m1 = ContentMedia::create($this->mediaAttrs())->id;
        $m2 = ContentMedia::create($this->mediaAttrs())->id;
        $m3 = ContentMedia::create($this->mediaAttrs())->id;
        // product 100 linked to all 3, product 200 to just 1
        $this->service->bulkLinkProducts([$m1, $m2, $m3], [100]);
        $this->service->bulkLinkProducts([$m1], [200]);
        $counts = $this->service->productCounts();
        $this->assertSame(3, $counts[100]);
        $this->assertSame(1, $counts[200]);
    }

    public function test_list_filters_by_product(): void
    {
        $m1 = ContentMedia::create($this->mediaAttrs());
        $m2 = ContentMedia::create($this->mediaAttrs());
        $m3 = ContentMedia::create($this->mediaAttrs());
        $this->service->linkProducts($m1, [42]);
        $this->service->linkProducts($m2, [42, 43]);
        // m3 has no products

        $this->assertSame(2, $this->service->list(['product' => 42])->total());
        $this->assertSame(1, $this->service->list(['product' => 43])->total());
        $this->assertSame(2, $this->service->list(['product' => '42,43'])->total());
    }

    public function test_list_filters_by_collection(): void
    {
        $m1 = ContentMedia::create($this->mediaAttrs());
        $m2 = ContentMedia::create($this->mediaAttrs());
        $this->service->linkCollections($m1, [77]);
        $this->service->linkCollections($m2, [88]);

        $this->assertSame(1, $this->service->list(['collection' => 77])->total());
        $this->assertSame(1, $this->service->list(['collection' => 88])->total());
        $this->assertSame(2, $this->service->list(['collection' => [77, 88]])->total());
    }

    public function test_preload_linked_ids_populates_appended_attributes(): void
    {
        $m1 = ContentMedia::create($this->mediaAttrs());
        $m2 = ContentMedia::create($this->mediaAttrs());
        $this->service->linkProducts($m1, [1, 2]);
        $this->service->linkCollections($m1, [10]);

        $collection = collect([$m1->fresh(), $m2->fresh()]);
        $this->service->preloadLinkedIds($collection);

        // Accessing the appended attribute should NOT hit the DB again because
        // the service already populated the cache. We verify values come back
        // correctly.
        $this->assertEqualsCanonicalizing([1, 2], $collection->get(0)->item_group_ids);
        $this->assertSame([10], $collection->get(0)->distribution_week_ids);
        $this->assertSame([], $collection->get(1)->item_group_ids);
        $this->assertSame([], $collection->get(1)->distribution_week_ids);
    }

    // ── Auto-link collection from product usage ──

    public function test_infer_collection_returns_null_for_empty_products(): void
    {
        $this->assertNull($this->service->inferCollectionForProducts([]));
    }

    public function test_infer_collection_returns_null_when_product_never_used_in_basket(): void
    {
        $this->assertNull($this->service->inferCollectionForProducts([9999]));
    }

    public function test_infer_collection_returns_week_when_product_is_in_exactly_one(): void
    {
        // Seed: one basket (week=42) with one post that uses product 100
        $basketId = \Illuminate\Support\Facades\DB::table('daily_baskets')->insertGetId([
            'distribution_week_id' => 42,
            'date' => now()->toDateString(),
            'status' => 'active',
        ]);
        $postId = \Illuminate\Support\Facades\DB::table('daily_basket_posts')->insertGetId([
            'daily_basket_id' => $basketId,
            'title' => 'Test post',
            'post_type' => 'photo',
            'stage' => 'idea',
            'priority' => 'medium',
            'target_platforms' => json_encode(['instagram']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('daily_basket_post_products')->insert([
            'daily_basket_post_id' => $postId,
            'item_group_id' => 100,
            'sort_order' => 0,
            'is_hero' => 1,
        ]);

        $this->assertSame(42, $this->service->inferCollectionForProducts([100]));
    }

    public function test_infer_collection_returns_null_when_product_is_in_two_weeks(): void
    {
        foreach ([11, 22] as $weekId) {
            $basketId = \Illuminate\Support\Facades\DB::table('daily_baskets')->insertGetId([
                'distribution_week_id' => $weekId,
                'date' => now()->subDays($weekId)->toDateString(),
                'status' => 'active',
            ]);
            $postId = \Illuminate\Support\Facades\DB::table('daily_basket_posts')->insertGetId([
                'daily_basket_id' => $basketId,
                'title' => 'Test post',
                'post_type' => 'photo',
                'stage' => 'idea',
                'priority' => 'medium',
                'target_platforms' => json_encode(['instagram']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            \Illuminate\Support\Facades\DB::table('daily_basket_post_products')->insert([
                'daily_basket_post_id' => $postId,
                'item_group_id' => 200,
                'sort_order' => 0,
                'is_hero' => 1,
            ]);
        }

        $this->assertNull($this->service->inferCollectionForProducts([200]));
    }

    public function test_auto_link_collection_from_products_attaches_when_singleton(): void
    {
        $basketId = \Illuminate\Support\Facades\DB::table('daily_baskets')->insertGetId([
            'distribution_week_id' => 77,
            'date' => now()->toDateString(),
            'status' => 'active',
        ]);
        $postId = \Illuminate\Support\Facades\DB::table('daily_basket_posts')->insertGetId([
            'daily_basket_id' => $basketId,
            'title' => 'Test post',
            'post_type' => 'photo',
            'stage' => 'idea',
            'priority' => 'medium',
            'target_platforms' => json_encode(['instagram']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('daily_basket_post_products')->insert([
            'daily_basket_post_id' => $postId,
            'item_group_id' => 500,
            'sort_order' => 0,
            'is_hero' => 1,
        ]);

        $media = ContentMedia::create($this->mediaAttrs());
        $linked = $this->service->autoLinkCollectionFromProducts($media, [500]);

        $this->assertSame(77, $linked);
        $this->assertSame([77], $media->fresh()->distribution_week_ids);
    }

    public function test_auto_link_collection_no_op_when_already_linked(): void
    {
        $basketId = \Illuminate\Support\Facades\DB::table('daily_baskets')->insertGetId([
            'distribution_week_id' => 88,
            'date' => now()->toDateString(),
            'status' => 'active',
        ]);
        $postId = \Illuminate\Support\Facades\DB::table('daily_basket_posts')->insertGetId([
            'daily_basket_id' => $basketId,
            'title' => 'Test post',
            'post_type' => 'photo',
            'stage' => 'idea',
            'priority' => 'medium',
            'target_platforms' => json_encode(['instagram']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('daily_basket_post_products')->insert([
            'daily_basket_post_id' => $postId,
            'item_group_id' => 600,
            'sort_order' => 0,
            'is_hero' => 1,
        ]);

        $media = ContentMedia::create($this->mediaAttrs());
        $this->service->linkCollections($media, [88]);
        $result = $this->service->autoLinkCollectionFromProducts($media->fresh(), [600]);

        $this->assertNull($result, 'Already linked — should return null');
    }

    // ── Existing tests continue below ──

    public function test_list_excludes_meta_imports_unless_imported_folder_or_all(): void
    {
        // Meta import record (path in the reserved prefix)
        ContentMedia::create($this->mediaAttrs([
            'folder' => 'imported',
            'stage' => 'final',
            'path' => 'content-planner/meta-imports/abc.jpg',
        ]));
        // Regular upload
        ContentMedia::create($this->mediaAttrs([
            'folder' => 'photos',
        ]));

        // Default (__all not set) → meta imports excluded
        $default = $this->service->list([]);
        $this->assertSame(1, $default->total());

        // folder=imported → include
        $imported = $this->service->list(['folder' => 'imported']);
        $this->assertSame(1, $imported->total());

        // folder=__all → include everything
        $all = $this->service->list(['folder' => '__all']);
        $this->assertSame(2, $all->total());
    }

    // ── Helpers ──

    private function mediaAttrs(array $overrides = []): array
    {
        return array_merge([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'user_id' => 1,
            'filename' => 'test-' . uniqid() . '.jpg',
            'original_filename' => 'test.jpg',
            'disk' => 'public',
            'path' => 'test/' . uniqid() . '.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'width' => 800,
            'height' => 600,
            'folder' => null,
            'stage' => 'raw',
        ], $overrides);
    }
}
