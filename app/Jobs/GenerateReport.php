<?php

namespace App\Jobs;

use App\Models\ReportSnapshot;
use App\Services\ReportingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateReport implements ShouldQueue
{
    use Queueable;

    public int $snapshotId;

    public function __construct(int $snapshotId)
    {
        $this->snapshotId = $snapshotId;
    }

    /**
     * Resolve the snapshot, compute the report through ReportingService and
     * move the snapshot to completed. Any exception is recorded on the
     * snapshot as a failed status with the error message, so a failed run is
     * always visible on the report library instead of silently dropping the
     * job.
     */
    public function handle(ReportingService $service): void
    {
        $snapshot = ReportSnapshot::findOrFail($this->snapshotId);

        try {
            $service->generate($snapshot);
        } catch (Throwable $exception) {
            $snapshot->markFailed($exception->getMessage());
        }
    }
}
