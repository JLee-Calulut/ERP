<?php

namespace Tests\Feature\Admin;

use App\Models\ReportSnapshot;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportsExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function manager(): User
    {
        $user = User::factory()->create();
        $user->assignRole('manager');

        return $user;
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        return $user;
    }

    private function completedSnapshot(?string $type = null): ReportSnapshot
    {
        return ReportSnapshot::query()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->where('status', ReportSnapshot::STATUS_COMPLETED)
            ->firstOrFail();
    }

    private function failedSalesSnapshot(): ReportSnapshot
    {
        return ReportSnapshot::query()
            ->where('type', ReportSnapshot::TYPE_SALES)
            ->where('status', ReportSnapshot::STATUS_FAILED)
            ->firstOrFail();
    }

    /**
     * Parse a streamed CSV export into an array of rows after dropping the
     * UTF-8 BOM that spreadsheet apps need.
     */
    private function csvRows(TestResponse $response): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', (string) $response->streamedContent());

        return array_values(array_filter(
            array_map(fn (string $line) => str_getcsv($line), explode("\n", trim($content, "\n\r"))),
            fn (array $row) => $row !== [''] && $row !== [null],
        ));
    }

    // ----------------------------------------------------------------------
    // Export authorization
    // ----------------------------------------------------------------------

    public function test_export_requires_authentication(): void
    {
        $snapshot = $this->completedSnapshot(ReportSnapshot::TYPE_INVENTORY);

        $this->get("/erp/reports/{$snapshot->id}/export")
            ->assertRedirect(route('erp.login'));
    }

    public function test_staff_without_reports_export_is_forbidden_from_exporting(): void
    {
        $snapshot = $this->completedSnapshot();

        $this->actingAs($this->staff())
            ->get("/erp/reports/{$snapshot->id}/export")
            ->assertForbidden();
    }

    public function test_a_user_without_reports_view_is_forbidden_from_all_new_routes(): void
    {
        $viewer = Role::create([
            'name' => 'Read-only Viewer',
            'slug' => 'viewer',
            'description' => 'Sees only the dashboard.',
        ]);
        $viewer->permissions()->sync([]);

        $user = User::factory()->create();
        $user->assignRole('viewer');
        $snapshot = $this->completedSnapshot();

        $this->actingAs($user)
            ->get('/erp/reports/history')
            ->assertForbidden();

        $this->actingAs($user)
            ->get("/erp/reports/{$snapshot->id}/export")
            ->assertForbidden();

        $this->actingAs($user)
            ->post("/erp/reports/{$snapshot->id}/regenerate")
            ->assertForbidden();
    }

    public function test_exporting_a_pending_snapshot_is_rejected(): void
    {
        $snapshot = ReportSnapshot::create([
            'type' => ReportSnapshot::TYPE_SALES,
            'title' => 'Sales Report',
            'parameters' => [],
            'status' => ReportSnapshot::STATUS_PENDING,
            'generated_by' => $this->admin()->id,
        ]);

        $this->actingAs($this->admin())
            ->get("/erp/reports/{$snapshot->id}/export")
            ->assertStatus(422);
    }

    // ----------------------------------------------------------------------
    // Export content
    // ----------------------------------------------------------------------

    public function test_admin_can_download_a_csv_export_with_filename_headers_and_rows(): void
    {
        $snapshot = $this->completedSnapshot(ReportSnapshot::TYPE_INVENTORY);

        $response = $this->actingAs($this->admin())
            ->get("/erp/reports/{$snapshot->id}/export");

        $response->assertOk();
        $response->assertDownload('inventory-report.csv');

        $rows = $this->csvRows($response);

        // Header row names each flattened column.
        $this->assertSame(['Category', 'Products', 'Units in stock', 'Valuation'], $rows[0]);

        // The seeded inventory report values its Beverages category at 248.
        $this->assertContains('Beverages', array_column($rows, 0));
        $beverages = collect($rows)->first(fn (array $row) => $row[0] === 'Beverages');
        $this->assertNotNull($beverages);
        $this->assertSame('248', $beverages[3]);
        $this->assertCount(1 + count($snapshot->payload['valuation_by_category']), $rows);
    }

    public function test_manager_can_export_an_income_statements_rows(): void
    {
        $snapshot = $this->completedSnapshot(ReportSnapshot::TYPE_INCOME_STATEMENT);

        $response = $this->actingAs($this->manager())
            ->get("/erp/reports/{$snapshot->id}/export");

        $response->assertOk();
        $response->assertDownload('income-statement-report.csv');

        $rows = $this->csvRows($response);

        $this->assertSame(['Section', 'Code', 'Account', 'Amount'], $rows[0]);

        // The seeded ledger books $12,998 of Sales Revenue under code 4010.
        $this->assertContains(
            ['Revenue', '4010', 'Sales Revenue', '12998'],
            $rows,
        );
        $this->assertCount(
            1 + count($snapshot->payload['revenue']) + count($snapshot->payload['expenses']),
            $rows,
        );
    }

    public function test_export_streams_every_report_type_with_its_own_shape(): void
    {
        $checks = [
            [
                'type' => ReportSnapshot::TYPE_SALES,
                'filename' => 'sales-report.csv',
                'headers' => ['Month', 'Revenue'],
            ],
            [
                'type' => ReportSnapshot::TYPE_INVENTORY,
                'filename' => 'inventory-report.csv',
                'headers' => ['Category', 'Products', 'Units in stock', 'Valuation'],
            ],
            [
                'type' => ReportSnapshot::TYPE_INCOME_STATEMENT,
                'filename' => 'income-statement-report.csv',
                'headers' => ['Section', 'Code', 'Account', 'Amount'],
            ],
            [
                'type' => ReportSnapshot::TYPE_BALANCE_SHEET,
                'filename' => 'balance-sheet-report.csv',
                'headers' => ['Section', 'Code', 'Account', 'Amount'],
            ],
            [
                'type' => ReportSnapshot::TYPE_PURCHASING,
                'filename' => 'purchasing-report.csv',
                'headers' => ['Supplier', 'Orders', 'PO value', 'Received value'],
            ],
        ];

        foreach ($checks as $check) {
            $snapshot = $this->completedSnapshot($check['type']);

            $response = $this->actingAs($this->manager())
                ->get("/erp/reports/{$snapshot->id}/export");

            $response->assertOk();
            $response->assertDownload($check['filename']);

            $rows = $this->csvRows($response);
            $this->assertSame($check['headers'], $rows[0], "{$check['type']} headers");
            $this->assertGreaterThan(1, count($rows), "{$check['type']} has data rows");
        }

        // Balance sheet rows carry section labels, codes and statement amounts.
        $balanceSheet = $this->completedSnapshot(ReportSnapshot::TYPE_BALANCE_SHEET);
        $inventoryAccount = collect($balanceSheet->payload['assets'])
            ->firstWhere('code', '1030');
        $response = $this->actingAs($this->manager())
            ->get("/erp/reports/{$balanceSheet->id}/export");

        $this->assertContains(
            ['Assets', '1030', 'Inventory', (string) $inventoryAccount['amount']],
            $this->csvRows($response),
        );

        // The purchasing export lists TechSource with its $1,140 received PO.
        $purchasing = $this->completedSnapshot(ReportSnapshot::TYPE_PURCHASING);
        $response = $this->actingAs($this->admin())
            ->get("/erp/reports/{$purchasing->id}/export");

        $this->assertContains(
            ['TechSource Distribution', '1', '1140', '1140'],
            $this->csvRows($response),
        );
    }

    // ----------------------------------------------------------------------
    // Snapshot history
    // ----------------------------------------------------------------------

    public function test_history_lists_statuses_generators_timestamps_and_errors(): void
    {
        $this->actingAs($this->staff())
            ->get('/erp/reports/history?type=sales')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Reporting/History')
                ->where('type', 'sales')
                ->has('types', 5)
                ->has('snapshots', 3)
                // Newest first: the regenerated completed run, the failed one,
                // then the original completed run.
                ->where('snapshots.0.status', 'completed')
                ->where('snapshots.0.generated_by', 'Manager User')
                ->where('snapshots.0.error', null)
                ->where('snapshots.1.status', 'failed')
                ->where('snapshots.1.generated_by', 'Admin User')
                ->where('snapshots.1.error', 'Sales report generation failed: the revenue query timed out. Regenerate produced a fresh snapshot.')
                ->where('snapshots.2.status', 'completed'));
    }

    public function test_history_without_a_filter_lists_every_snapshot(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/reports/history')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Reporting/History')
                ->where('type', null)
                ->has('snapshots', 7)
                ->has('types', 5));
    }

    public function test_history_rejects_an_unknown_report_type(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/reports/history?type=bogus')
            ->assertStatus(422);
    }

    // ----------------------------------------------------------------------
    // Regeneration
    // ----------------------------------------------------------------------

    public function test_regenerating_a_snapshot_dispatches_a_fresh_completed_run(): void
    {
        $failed = $this->failedSalesSnapshot();
        $beforeCount = ReportSnapshot::count();

        $this->actingAs($this->admin())
            ->post("/erp/reports/{$failed->id}/regenerate")
            ->assertRedirect(route('erp.reports.history', ['type' => ReportSnapshot::TYPE_SALES]));

        $fresh = ReportSnapshot::query()
            ->where('id', '>', $failed->id)
            ->where('type', ReportSnapshot::TYPE_SALES)
            ->latest('id')
            ->firstOrFail();

        // The fresh run went through the same pipeline: pending then completed
        // (the suite runs the queue in sync), reusing the snapshot's parameters.
        $this->assertSame(ReportSnapshot::STATUS_COMPLETED, $fresh->status);
        $this->assertNull($fresh->error);
        $this->assertSame($failed->parameters, $fresh->parameters);
        $this->assertSame(6, $fresh->payload['summary']['order_count']);
        $this->assertSame(1986, $fresh->payload['summary']['total_revenue']);

        // History is preserved around the retry: the failed run stays failed
        // and the total snapshot count grows by exactly one.
        $failed->refresh();
        $this->assertSame(ReportSnapshot::STATUS_FAILED, $failed->status);
        $this->assertStringContainsString('timed out', $failed->error);
        $this->assertSame($beforeCount + 1, ReportSnapshot::count());
        $this->assertGreaterThan($failed->id, $fresh->id);
    }

    public function test_staff_with_reports_view_can_regenerate_a_failed_snapshot(): void
    {
        $failed = $this->failedSalesSnapshot();

        $this->actingAs($this->staff())
            ->post("/erp/reports/{$failed->id}/regenerate")
            ->assertRedirect(route('erp.reports.history', ['type' => ReportSnapshot::TYPE_SALES]));

        $fresh = ReportSnapshot::where('type', ReportSnapshot::TYPE_SALES)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(ReportSnapshot::STATUS_COMPLETED, $fresh->status);
        $this->assertNull($fresh->error);
    }

    public function test_regenerating_a_pending_snapshot_is_rejected(): void
    {
        $snapshot = ReportSnapshot::create([
            'type' => ReportSnapshot::TYPE_SALES,
            'title' => 'Sales Report',
            'parameters' => [],
            'status' => ReportSnapshot::STATUS_PENDING,
            'generated_by' => $this->admin()->id,
        ]);

        $this->actingAs($this->admin())
            ->post("/erp/reports/{$snapshot->id}/regenerate")
            ->assertStatus(422);
    }
}
