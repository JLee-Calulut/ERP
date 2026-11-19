<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportRequest;
use App\Jobs\GenerateReport;
use App\Models\ReportSnapshot;
use App\Services\ReportingExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Report library: one card per report type with a "Run report" button
     * and the latest snapshot's status underneath.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', ReportSnapshot::class);

        $latest = ReportSnapshot::query()
            ->whereIn('type', ReportSnapshot::TYPES)
            ->latest('id')
            ->get()
            ->keyBy('type');

        $reports = collect($this->reportTypes())
            ->map(function (array $report) use ($latest) {
                $snapshot = $latest->get($report['type']);

                return $report + [
                    'latest_snapshot' => $snapshot ? [
                        'id' => $snapshot->id,
                        'status' => $snapshot->status,
                        'status_label' => $snapshot->statusLabel(),
                        'generated_at' => $snapshot->updated_at?->toDateTimeString(),
                        'error' => $snapshot->error,
                    ] : null,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Erp/Reporting/Index', [
            'reports' => $reports,
        ]);
    }

    /**
     * Kick off a new generation: a pending snapshot is persisted and its
     * GenerateReport job is queued. The job runs immediately under the sync
     * queue (tests) or via the database queue in production.
     */
    public function store(ReportRequest $request): RedirectResponse
    {
        Gate::authorize('create', ReportSnapshot::class);

        $type = $request->input('type');
        $report = collect($this->reportTypes())->firstWhere('type', $type);

        $snapshot = ReportSnapshot::create([
            'type' => $type,
            'title' => $report['label'] ?? ucfirst($type).' report',
            'parameters' => [
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ],
            'status' => ReportSnapshot::STATUS_PENDING,
            'generated_by' => $request->user()?->id,
        ]);

        GenerateReport::dispatch($snapshot->id);

        return redirect(route('erp.reports.index'))
            ->with('success', 'Report generation started.');
    }

    /**
     * Render a completed snapshot: its summary cards, tables and the simple
     * bar chart for the monthly revenue series.
     */
    public function show(ReportSnapshot $reportSnapshot): Response
    {
        Gate::authorize('view', $reportSnapshot);

        abort_unless($reportSnapshot->isCompleted(), 422);

        $page = match ($reportSnapshot->type) {
            ReportSnapshot::TYPE_SALES => 'Erp/Reporting/Sales',
            ReportSnapshot::TYPE_INVENTORY => 'Erp/Reporting/Inventory',
            ReportSnapshot::TYPE_INCOME_STATEMENT => 'Erp/Reporting/IncomeStatement',
            ReportSnapshot::TYPE_BALANCE_SHEET => 'Erp/Reporting/BalanceSheet',
            ReportSnapshot::TYPE_PURCHASING => 'Erp/Reporting/Purchasing',
            default => 'Erp/Reporting/Inventory',
        };

        return Inertia::render($page, [
            'snapshot' => [
                'id' => $reportSnapshot->id,
                'type' => $reportSnapshot->type,
                'title' => $reportSnapshot->title,
                'status' => $reportSnapshot->status,
                'generated_at' => $reportSnapshot->updated_at?->toDateTimeString(),
                'payload' => $reportSnapshot->payload,
            ],
        ]);
    }

    /**
     * Snapshot history for one report type (or all types when no filter is
     * given): every past run with its status, generator, timestamp and the
     * failure message, newest first.
     */
    public function history(Request $request): Response
    {
        Gate::authorize('viewAny', ReportSnapshot::class);

        $type = $request->query('type');

        abort_unless(
            $type === null || in_array($type, ReportSnapshot::TYPES, true),
            422,
        );

        $snapshots = ReportSnapshot::query()
            ->with('generatedBy')
            ->when($type, fn ($query) => $query->where('type', $type))
            ->orderByDesc('id')
            ->get()
            ->map(fn (ReportSnapshot $snapshot): array => [
                'id' => $snapshot->id,
                'type' => $snapshot->type,
                'type_label' => $snapshot->typeLabel(),
                'title' => $snapshot->title,
                'status' => $snapshot->status,
                'status_label' => $snapshot->statusLabel(),
                'generated_by' => $snapshot->generatedBy?->name,
                'generated_at' => $snapshot->updated_at?->toDateTimeString(),
                'error' => $snapshot->error,
            ])
            ->values()
            ->all();

        return Inertia::render('Erp/Reporting/History', [
            'types' => $this->reportTypes(),
            'type' => $type,
            'snapshots' => $snapshots,
        ]);
    }

    /**
     * Stream a completed snapshot's payload as a CSV download. The file is
     * named after the report type and gated by ReportPolicy::export() so only
     * users with reports.export can download it.
     */
    public function export(ReportSnapshot $reportSnapshot): StreamedResponse
    {
        Gate::authorize('export', $reportSnapshot);

        abort_unless($reportSnapshot->isCompleted(), 422);

        $export = app(ReportingExporter::class)->export($reportSnapshot);

        return response()->streamDownload(function () use ($export) {
            $stream = fopen('php://output', 'w');

            // UTF-8 BOM so spreadsheet apps read the header row correctly.
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $export['columns']);

            foreach ($export['rows'] as $row) {
                fputcsv($stream, $row);
            }

            fclose($stream);
        }, $reportSnapshot->exportFilename(), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Regenerate a finished snapshot: re-dispatch the exact same report type
     * and parameters through the same pipeline as a brand-new run, so a fresh
     * completed snapshot appears on top of the history while every past run
     * (including a failed one being retried) stays in place.
     */
    public function regenerate(Request $request, ReportSnapshot $reportSnapshot): RedirectResponse
    {
        Gate::authorize('create', ReportSnapshot::class);

        // A pending run is already in flight; only a finished or a failed
        // snapshot can be regenerated.
        abort_unless($reportSnapshot->isCompleted() || $reportSnapshot->isFailed(), 422);

        $snapshot = ReportSnapshot::create([
            'type' => $reportSnapshot->type,
            'title' => $reportSnapshot->title,
            'parameters' => $reportSnapshot->parameters ?: [],
            'status' => ReportSnapshot::STATUS_PENDING,
            'generated_by' => $request->user()?->id,
        ]);

        GenerateReport::dispatch($snapshot->id);

        return redirect(route('erp.reports.history', ['type' => $reportSnapshot->type]))
            ->with('success', 'Report regeneration started.');
    }

    /**
     * Static descriptors shown on the report library.
     *
     * @return array<int, array{type: string, label: string, icon: string, description: string}>
     */
    private function reportTypes(): array
    {
        return [
            [
                'type' => ReportSnapshot::TYPE_SALES,
                'label' => 'Sales Report',
                'icon' => '💰',
                'description' => 'Revenue by month, top products and customers, invoice count and average order value.',
            ],
            [
                'type' => ReportSnapshot::TYPE_INVENTORY,
                'label' => 'Inventory Report',
                'icon' => '📦',
                'description' => 'Stock valuation by category, low-stock items and in/out movement counts.',
            ],
            [
                'type' => ReportSnapshot::TYPE_INCOME_STATEMENT,
                'label' => 'Income Statement',
                'icon' => '📈',
                'description' => 'Revenue and expenses from the journal, with a net income total.',
            ],
            [
                'type' => ReportSnapshot::TYPE_BALANCE_SHEET,
                'label' => 'Balance Sheet',
                'icon' => '⚖️',
                'description' => 'Assets, liabilities and equity grouped from the journal, with retained earnings.',
            ],
            [
                'type' => ReportSnapshot::TYPE_PURCHASING,
                'label' => 'Purchasing Report',
                'icon' => '🚚',
                'description' => 'Spend by supplier, PO value by status and received-vs-pending summary.',
            ],
        ];
    }
}
