<?php

namespace Tests\Unit\Marketing;

use App\Enums\DailyBasketPostType;
use App\Http\Controllers\Marketing\DailyBasketController;
use App\Models\DailyBasketPost;
use App\Services\ContentPlanner\ContentPostService;
use App\Services\DisApiClient;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

/**
 * Shporta Ditore v2 — coverage computation.
 *
 * The rail + summary strip depend on this payload getting three things
 * right:
 *   1. which collection products belong to the basket's day
 *      (primary + re-marketing assignments)
 *   2. how many posts feature each of those products (posts_count badge)
 *   3. the rolled-up totals (covered/uncovered, posts_by_type, stock, vlere)
 *
 * Extracted as a pure helper (computeCoverageData) so the unit test does
 * not need DIS or a database — we pass already-fetched pivot counts and
 * collection products straight in.
 */
class DailyBasketCoverageTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** (a) Basket with 0 posts — every product renders as uncovered. */
    public function test_zero_posts_mark_every_product_uncovered(): void
    {
        $result = $this->compute(
            basketDate: '2026-04-24',
            products: [
                $this->product(id: 101, assignedDates: ['2026-04-24']),
                $this->product(id: 102, assignedDates: ['2026-04-24']),
            ],
            pivotCountByProduct: [],
            posts: [],
        );

        $this->assertCount(2, $result['products']);
        foreach ($result['products'] as $p) {
            $this->assertSame(0, $p['posts_count']);
        }
        $this->assertSame(2, $result['summary']['products_total']);
        $this->assertSame(0, $result['summary']['products_covered']);
        $this->assertSame(2, $result['summary']['products_uncovered']);
        $this->assertSame(0, $result['summary']['posts_total']);
    }

    /** (b) One post with one product → exactly that product gets +1. */
    public function test_single_post_single_product_covers_only_that_product(): void
    {
        $result = $this->compute(
            basketDate: '2026-04-24',
            products: [
                $this->product(id: 101, assignedDates: ['2026-04-24']),
                $this->product(id: 102, assignedDates: ['2026-04-24']),
            ],
            pivotCountByProduct: [101 => 1],
            posts: [$this->fakePost(DailyBasketPostType::PHOTO)],
        );

        $byId = collect($result['products'])->keyBy('item_group_id');
        $this->assertSame(1, $byId[101]['posts_count']);
        $this->assertSame(0, $byId[102]['posts_count']);
        $this->assertSame(1, $result['summary']['products_covered']);
        $this->assertSame(1, $result['summary']['products_uncovered']);
        $this->assertSame(1, $result['summary']['posts_by_type']['photo']);
    }

    /** (c) A combo post attaching two products → both get +1. */
    public function test_combo_post_credits_each_product_once(): void
    {
        $result = $this->compute(
            basketDate: '2026-04-24',
            products: [
                $this->product(id: 101, assignedDates: ['2026-04-24']),
                $this->product(id: 102, assignedDates: ['2026-04-24']),
                $this->product(id: 103, assignedDates: ['2026-04-24']),
            ],
            pivotCountByProduct: [101 => 1, 102 => 1],
            posts: [$this->fakePost(DailyBasketPostType::REEL)],
        );

        $byId = collect($result['products'])->keyBy('item_group_id');
        $this->assertSame(1, $byId[101]['posts_count']);
        $this->assertSame(1, $byId[102]['posts_count']);
        $this->assertSame(0, $byId[103]['posts_count']);
        $this->assertSame(2, $result['summary']['products_covered']);
        $this->assertSame(1, $result['summary']['products_uncovered']);
        $this->assertSame(1, $result['summary']['posts_by_type']['reel']);
    }

    /** (d) Two posts both featuring the same product → that product gets 2. */
    public function test_same_product_in_two_posts_accumulates_count(): void
    {
        $result = $this->compute(
            basketDate: '2026-04-24',
            products: [
                $this->product(id: 101, assignedDates: ['2026-04-24']),
            ],
            pivotCountByProduct: [101 => 2],
            posts: [
                $this->fakePost(DailyBasketPostType::STORY),
                $this->fakePost(DailyBasketPostType::CAROUSEL),
            ],
        );

        $this->assertSame(2, $result['products'][0]['posts_count']);
        $this->assertSame(1, $result['summary']['products_covered']);
        $this->assertSame(0, $result['summary']['products_uncovered']);
        $this->assertSame(2, $result['summary']['posts_total']);
        $this->assertSame(1, $result['summary']['posts_by_type']['story']);
        $this->assertSame(1, $result['summary']['posts_by_type']['carousel']);
    }

    /** Products assigned to other days are filtered OUT of today's rail. */
    public function test_products_assigned_to_other_days_are_excluded(): void
    {
        $result = $this->compute(
            basketDate: '2026-04-24',
            products: [
                $this->product(id: 101, assignedDates: ['2026-04-24']),
                $this->product(id: 102, assignedDates: ['2026-04-25']),
                $this->product(id: 103, assignedDates: []),
            ],
            pivotCountByProduct: [],
            posts: [],
        );

        $this->assertCount(1, $result['products']);
        $this->assertSame(101, $result['products'][0]['item_group_id']);
    }

    /** total_value = price × stock; summary rolls them up. */
    public function test_summary_totals_roll_up_stock_and_retail_value(): void
    {
        $result = $this->compute(
            basketDate: '2026-04-24',
            products: [
                $this->product(id: 101, assignedDates: ['2026-04-24'], price: 2990, stock: 4),
                $this->product(id: 102, assignedDates: ['2026-04-24'], price: 1500, stock: 10),
            ],
            pivotCountByProduct: [],
            posts: [],
        );

        $this->assertSame(14, $result['summary']['stok_total']);
        $this->assertSame(26960.0, $result['summary']['vlere_total']);
        $this->assertSame(11960.0, $result['products'][0]['total_value']);
        $this->assertSame(15000.0, $result['products'][1]['total_value']);
    }

    // ── helpers ──────────────────────────────────────────────

    private function compute(
        string $basketDate,
        array $products,
        array $pivotCountByProduct,
        array $posts,
    ): array {
        $disApi = Mockery::mock(DisApiClient::class);
        $contentPost = Mockery::mock(ContentPostService::class);
        $controller = new DailyBasketController($disApi, $contentPost);

        $reflection = new ReflectionClass($controller);
        $m = $reflection->getMethod('computeCoverageData');
        $m->setAccessible(true);

        return $m->invokeArgs($controller, [
            $basketDate,
            $products,
            $pivotCountByProduct,
            $posts,
        ]);
    }

    /**
     * Shape matches what loadCollectionProducts() returns — keeps the test
     * honest to the real backend contract.
     */
    private function product(int $id, array $assignedDates, float $price = 2990, int $stock = 10): array
    {
        return [
            'id' => $id,
            'code' => 'ZA-'.str_pad((string) $id, 3, '0', STR_PAD_LEFT),
            'name' => 'Produkt #'.$id,
            'vendor_name' => null,
            'image_url' => 'https://cdn.test/'.$id.'.jpg',
            'avg_price' => null,
            'pricelist_price' => $price,
            'classification' => 'fashion',
            'total_stock' => $stock,
            'assigned_dates' => array_map(
                fn (string $d, int $idx) => ['id' => $idx + 1, 'date' => $d, 'is_primary' => $idx === 0],
                $assignedDates,
                array_keys($assignedDates),
            ),
        ];
    }

    /**
     * Lightweight fake post — we only read `post_type` in the helper, so a
     * plain object with that one attribute is enough; no DB required.
     */
    private function fakePost(DailyBasketPostType $type): DailyBasketPost
    {
        $post = new DailyBasketPost();
        $post->post_type = $type;
        return $post;
    }
}
