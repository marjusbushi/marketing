<?php

namespace App\Services\Marketing;

use App\Models\Marketing\CreativeBrief;
use App\Models\Marketing\RenderJob;

/**
 * Render Job lifecycle — queue, transition, and completion.
 *
 * The actual Horizon job (App\Jobs\Marketing\RenderVideoJob) is dispatched
 * by the controller that handles the "Finalize video" click. This service
 * owns the DB transitions; the Horizon job mutates status via these
 * methods so all writes share a single entry point.
 */
class RenderJobService
{
    public function queue(CreativeBrief $brief): RenderJob
    {
        $job = RenderJob::query()->create([
            'creative_brief_id' => $brief->id,
            'status'            => RenderJob::STATUS_QUEUED,
        ]);

        $brief->render_job_id = $job->id;
        $brief->save();

        return $job;
    }

    public function markRendering(RenderJob $job): RenderJob
    {
        $job->status = RenderJob::STATUS_RENDERING;
        $job->started_at = now();
        $job->save();

        return $job;
    }

    public function markCompleted(
        RenderJob $job,
        string $outputPath,
        ?string $thumbnailPath = null,
        ?int $durationSeconds = null,
        ?int $sizeBytes = null,
    ): RenderJob {
        $job->status = RenderJob::STATUS_COMPLETED;
        $job->output_path = $outputPath;
        $job->output_thumbnail = $thumbnailPath;
        $job->output_duration_seconds = $durationSeconds;
        $job->output_size_bytes = $sizeBytes;
        $job->completed_at = now();
        $job->save();

        return $job;
    }

    public function markFailed(RenderJob $job, string $errorMessage): RenderJob
    {
        $job->status = RenderJob::STATUS_FAILED;
        $job->error_message = $errorMessage;
        $job->completed_at = now();
        $job->save();

        return $job;
    }

    public function latestForBrief(CreativeBrief $brief): ?RenderJob
    {
        return RenderJob::query()
            ->where('creative_brief_id', $brief->id)
            ->orderByDesc('id')
            ->first();
    }
}
