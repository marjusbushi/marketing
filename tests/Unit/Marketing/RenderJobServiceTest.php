<?php

namespace Tests\Unit\Marketing;

use App\Models\Marketing\CreativeBrief;
use App\Models\Marketing\RenderJob;
use App\Services\Marketing\RenderJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenderJobServiceTest extends TestCase
{
    use RefreshDatabase;

    private RenderJobService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RenderJobService();
    }

    public function test_queue_creates_job_and_links_brief(): void
    {
        $brief = CreativeBrief::query()->create(['post_type' => 'reel', 'source' => 'manual']);

        $job = $this->service->queue($brief);

        $this->assertSame(RenderJob::STATUS_QUEUED, $job->status);
        $this->assertSame($job->id, $brief->refresh()->render_job_id);
    }

    public function test_status_transitions(): void
    {
        $brief = CreativeBrief::query()->create(['post_type' => 'reel', 'source' => 'manual']);
        $job = $this->service->queue($brief);

        $this->service->markRendering($job);
        $this->assertSame(RenderJob::STATUS_RENDERING, $job->refresh()->status);
        $this->assertNotNull($job->started_at);

        $this->service->markCompleted($job, '/renders/out.mp4', '/renders/out.jpg', 15, 1_234_567);
        $job->refresh();
        $this->assertSame(RenderJob::STATUS_COMPLETED, $job->status);
        $this->assertSame('/renders/out.mp4', $job->output_path);
        $this->assertSame(15, $job->output_duration_seconds);
        $this->assertTrue($job->isTerminal());
    }

    public function test_mark_failed_records_error(): void
    {
        $brief = CreativeBrief::query()->create(['post_type' => 'reel', 'source' => 'manual']);
        $job = $this->service->queue($brief);

        $this->service->markFailed($job, 'FFmpeg crashed on frame 42');

        $job->refresh();
        $this->assertSame(RenderJob::STATUS_FAILED, $job->status);
        $this->assertSame('FFmpeg crashed on frame 42', $job->error_message);
        $this->assertTrue($job->isTerminal());
    }
}
