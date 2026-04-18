<?php

namespace Tests\Unit\Marketing;

use App\Http\Controllers\Marketing\DailyBasketController;
use App\Services\ContentPlanner\ContentPostService;
use App\Services\DisApiClient;
use Illuminate\Support\Facades\Cache;
use Mockery;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

/**
 * End-to-end shape tests for the DIS → daily-basket cross-DB flow.
 *
 * The post-redesign architecture (frontend-filter, see Decision #6) puts the
 * burden of "which products belong to which day" on the front-end. The backend
 * just propagates `assigned_dates` metadata through. These tests pin that
 * contract so a future refactor can't silently drop the field.
 */
class DailyBasketAssignmentTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_show_returns_available_products_with_assigned_dates_field(): void
    {
        $products = $this->invokeLoadCollectionProducts(weekId: 5, weekPayload: [
            'item_groups' => [
                ['id' => 101, 'code' => 'ZA-001', 'name' => 'A', 'assigned_dates' => [
                    ['id' => 1, 'date' => '2026-04-24', 'is_primary' => true],
                ]],
                ['id' => 102, 'code' => 'ZA-002', 'name' => 'B', 'assigned_dates' => []],
                ['id' => 103, 'code' => 'ZA-003', 'name' => 'C'], // missing key
            ],
        ]);

        $this->assertCount(3, $products);
        foreach ($products as $p) {
            $this->assertArrayHasKey('assigned_dates', $p, 'Cdo produkt duhet te kete fushen assigned_dates');
            $this->assertIsArray($p['assigned_dates']);
        }

        $this->assertCount(1, $products[0]['assigned_dates']);
        $this->assertSame(0, count($products[1]['assigned_dates']));
        $this->assertSame(0, count($products[2]['assigned_dates']));
    }

    public function test_show_passes_through_primary_and_remarketing_assignments(): void
    {
        $products = $this->invokeLoadCollectionProducts(weekId: 7, weekPayload: [
            'item_groups' => [
                ['id' => 101, 'code' => 'X', 'name' => 'X', 'assigned_dates' => [
                    ['id' => 11, 'date' => '2026-04-24', 'is_primary' => true],
                    ['id' => 12, 'date' => '2026-04-28', 'is_primary' => false],
                ]],
            ],
        ]);

        $assignments = $products[0]['assigned_dates'];
        $this->assertCount(2, $assignments);

        $this->assertSame(11, $assignments[0]['id']);
        $this->assertSame('2026-04-24', $assignments[0]['date']);
        $this->assertTrue($assignments[0]['is_primary']);

        $this->assertSame(12, $assignments[1]['id']);
        $this->assertSame('2026-04-28', $assignments[1]['date']);
        $this->assertFalse($assignments[1]['is_primary']);
    }

    public function test_show_works_when_no_assignments_exist(): void
    {
        $products = $this->invokeLoadCollectionProducts(weekId: 9, weekPayload: [
            'item_groups' => [
                ['id' => 1, 'code' => 'A', 'name' => 'A'], // no assigned_dates key at all
                ['id' => 2, 'code' => 'B', 'name' => 'B', 'assigned_dates' => []],
            ],
        ]);

        $this->assertCount(2, $products);
        // Frontend interpreton kete si rast 'fallback' (shfaq tere kolekcionin).
        // Backend nuk ben asnje filter — kthen produktet me assigned_dates=[].
        foreach ($products as $p) {
            $this->assertSame([], $p['assigned_dates']);
        }
    }

    public function test_show_handles_dis_api_failure_gracefully(): void
    {
        $disApi = Mockery::mock(DisApiClient::class);
        $disApi->shouldReceive('getWeek')
            ->once()
            ->with(99)
            ->andThrow(new RuntimeException('DIS down'));

        $controller = $this->makeController($disApi);
        Cache::flush();
        $products = $this->callPrivate($controller, 'loadCollectionProducts', [99]);

        // Sjellja ekzistuese: errors logohen via report() dhe returnohet array bosh
        // — daily-basket UI mbetet i perdorshem edhe kur DIS eshte i pakapshem.
        $this->assertSame([], $products);
    }

    public function test_assignment_field_casts_are_consistent(): void
    {
        // Defensive: assigned_dates payload from DIS arrives as JSON-decoded
        // associative arrays. Cast guarantees prevent type drift downstream
        // (e.g. is_primary as the string "1" instead of bool true).
        $products = $this->invokeLoadCollectionProducts(weekId: 11, weekPayload: [
            'item_groups' => [
                ['id' => 1, 'code' => 'A', 'name' => 'A', 'assigned_dates' => [
                    ['id' => '42', 'date' => '2026-04-24', 'is_primary' => 1],
                    ['id' => 43,   'date' => '2026-04-25', 'is_primary' => '0'],
                ]],
            ],
        ]);

        $a = $products[0]['assigned_dates'];
        $this->assertSame(42, $a[0]['id']);
        $this->assertTrue($a[0]['is_primary']);
        $this->assertSame(43, $a[1]['id']);
        $this->assertFalse($a[1]['is_primary']);
    }

    // ─── helpers ──────────────────────────────────────────────

    private function invokeLoadCollectionProducts(int $weekId, array $weekPayload): array
    {
        $disApi = Mockery::mock(DisApiClient::class);
        $disApi->shouldReceive('getWeek')
            ->once()
            ->with($weekId)
            ->andReturn($weekPayload);

        Cache::flush();

        $controller = $this->makeController($disApi);
        return $this->callPrivate($controller, 'loadCollectionProducts', [$weekId]);
    }

    private function makeController(DisApiClient $disApi): DailyBasketController
    {
        $contentPost = Mockery::mock(ContentPostService::class);
        return new DailyBasketController($disApi, $contentPost);
    }

    private function callPrivate(object $obj, string $method, array $args)
    {
        $reflection = new ReflectionClass($obj);
        $m = $reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($obj, $args);
    }
}
