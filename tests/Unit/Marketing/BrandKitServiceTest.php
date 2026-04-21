<?php

namespace Tests\Unit\Marketing;

use App\Models\Marketing\BrandKit;
use App\Services\Marketing\BrandKitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BrandKitServiceTest extends TestCase
{
    use RefreshDatabase;

    private BrandKitService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BrandKitService();
    }

    public function test_get_creates_singleton_when_missing(): void
    {
        $this->assertSame(0, BrandKit::query()->count());

        $kit = $this->service->get();

        $this->assertInstanceOf(BrandKit::class, $kit);
        $this->assertSame(1, BrandKit::query()->count());
    }

    public function test_get_returns_same_instance_on_second_call(): void
    {
        $a = $this->service->get();
        $b = $this->service->get();

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, BrandKit::query()->count());
    }

    public function test_update_writes_and_invalidates_cache(): void
    {
        $this->service->get();

        $this->service->update(['voice_sq' => 'Ton i drejtpërdrejtë.']);

        $fresh = BrandKit::query()->first();
        $this->assertSame('Ton i drejtpërdrejtë.', $fresh->voice_sq);
    }

    public function test_forget_clears_cache(): void
    {
        $this->service->get();

        Cache::shouldReceive('forget')->once()->with('marketing.brand_kit.v1');

        $this->service->forget();
    }
}
