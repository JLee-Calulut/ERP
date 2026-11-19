<?php

namespace Database\Seeders;

use App\Models\ReportSnapshot;
use App\Models\User;
use App\Services\ReportingService;
use Illuminate\Database\Seeder;

class ReportingSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(ReportingService::class);
        $admin = User::where('email', 'admin@erp.test')->first();
        $manager = User::where('email', 'manager@erp.test')->first();

        $titles = [
            ReportSnapshot::TYPE_SALES => 'Sales Report',
            ReportSnapshot::TYPE_INVENTORY => 'Inventory Report',
            ReportSnapshot::TYPE_INCOME_STATEMENT => 'Income Statement',
            ReportSnapshot::TYPE_BALANCE_SHEET => 'Balance Sheet',
            ReportSnapshot::TYPE_PURCHASING => 'Purchasing Report',
        ];

        // One completed snapshot per report type, computed through the same
        // ReportingService the queued job calls, so the report library shows
        // believable history on a fresh seed.
        foreach (ReportSnapshot::TYPES as $type) {
            $snapshot = ReportSnapshot::create([
                'type' => $type,
                'title' => $titles[$type],
                'parameters' => ['date_from' => null, 'date_to' => null],
                'status' => ReportSnapshot::STATUS_PENDING,
                'generated_by' => $admin?->id,
            ]);

            $service->generate($snapshot);
        }

        // One failed-then-regenerated sales run on top of the seeded snapshot,
        // so both the error state and the regeneration path are demonstrable
        // on a fresh seed: the failure is visible in the history list, and the
        // retry produced a fresh completed snapshot above it.
        $failed = ReportSnapshot::create([
            'type' => ReportSnapshot::TYPE_SALES,
            'title' => $titles[ReportSnapshot::TYPE_SALES],
            'parameters' => ['date_from' => null, 'date_to' => null],
            'status' => ReportSnapshot::STATUS_PENDING,
            'generated_by' => $admin?->id,
        ]);

        $failed->markFailed(
            'Sales report generation failed: the revenue query timed out. Regenerate produced a fresh snapshot.'
        );

        $retried = ReportSnapshot::create([
            'type' => ReportSnapshot::TYPE_SALES,
            'title' => $titles[ReportSnapshot::TYPE_SALES],
            'parameters' => ['date_from' => null, 'date_to' => null],
            'status' => ReportSnapshot::STATUS_PENDING,
            'generated_by' => $manager?->id,
        ]);

        $service->generate($retried);
    }
}
