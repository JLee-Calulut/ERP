<?php

namespace Tests\Feature\Admin;

use App\Jobs\GenerateReport;
use App\Models\Permission;
use App\Models\ReportSnapshot;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportsTest extends TestCase
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

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        return $user;
    }

    // ----------------------------------------------------------------------
    // Authorization
    // ----------------------------------------------------------------------

    public function test_the_report_library_requires_authentication(): void
    {
        $this->get('/erp/reports')
            ->assertRedirect(route('erp.login'));
    }

    public function test_reports_require_the_reports_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/erp/reports')
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/erp/reports', ['type' => ReportSnapshot::TYPE_SALES])
            ->assertForbidden();
    }

    public function test_a_role_without_reports_view_cannot_access_reports(): void
    {
        $viewer = Role::create([
            'name' => 'Read-only Viewer',
            'slug' => 'viewer',
            'description' => 'Sees only the dashboard.',
        ]);
        $viewer->permissions()->sync(
            Permission::where('slug', 'dashboard.view')->pluck('id')
        );

        $user = User::factory()->create();
        $user->assignRole('viewer');

        $this->actingAs($user)
            ->get('/erp/reports')
            ->assertForbidden();
    }

    public function test_staff_with_reports_view_can_open_the_report_library(): void
    {
        $this->actingAs($this->staff())
            ->get('/erp/reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Reporting/Index')
                // The ReportingSeeder ships one completed snapshot per type:
                // sales, inventory, income statement, balance sheet, purchasing.
                ->has('reports', 5)
                ->where('reports.0.type', 'sales')
                ->where('reports.0.latest_snapshot.status', 'completed')
                ->where('reports.1.type', 'inventory')
                ->where('reports.1.latest_snapshot.status', 'completed')
                ->where('reports.2.type', 'income_statement')
                ->where('reports.2.latest_snapshot.status', 'completed')
                ->where('reports.3.type', 'balance_sheet')
                ->where('reports.3.latest_snapshot.status', 'completed')
                ->where('reports.4.type', 'purchasing')
                ->where('reports.4.latest_snapshot.status', 'completed'));
    }

    public function test_staff_with_reports_view_can_open_a_completed_snapshot(): void
    {
        $snapshot = ReportSnapshot::where('type', ReportSnapshot::TYPE_SALES)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->actingAs($this->staff())
            ->get("/erp/reports/{$snapshot->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Reporting/Sales')
                ->where('snapshot.id', $snapshot->id)
                ->where('snapshot.payload.summary.order_count', 6)
                ->where('snapshot.payload.summary.total_revenue', 1986)
                ->where('snapshot.payload.summary.invoice_count', 6));
    }

    public function test_a_pending_snapshot_cannot_be_opened_as_a_report(): void
    {
        $snapshot = ReportSnapshot::create([
            'type' => ReportSnapshot::TYPE_SALES,
            'title' => 'Sales Report',
            'parameters' => [],
            'status' => ReportSnapshot::STATUS_PENDING,
            'generated_by' => $this->staff()->id,
        ]);

        $this->actingAs($this->staff())
            ->get("/erp/reports/{$snapshot->id}")
            ->assertStatus(422);
    }

    // ----------------------------------------------------------------------
    // Running reports
    // ----------------------------------------------------------------------

    public function test_running_a_report_stores_a_pending_snapshot_and_dispatches_the_job(): void
    {
        Queue::fake();

        $this->actingAs($this->staff())
            ->post('/erp/reports', ['type' => ReportSnapshot::TYPE_SALES])
            ->assertRedirect(route('erp.reports.index'));

        Queue::assertPushed(GenerateReport::class);

        $this->assertDatabaseHas('report_snapshots', [
            'type' => ReportSnapshot::TYPE_SALES,
            'status' => ReportSnapshot::STATUS_PENDING,
        ]);

        $snapshot = ReportSnapshot::where('type', ReportSnapshot::TYPE_SALES)
            ->latest('id')
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertSame(['date_from' => null, 'date_to' => null], $snapshot->parameters);
    }

    public function test_running_the_sales_report_stores_the_computed_payload(): void
    {
        config(['queue.default' => 'sync']);

        $this->actingAs($this->staff())
            ->post('/erp/reports', ['type' => ReportSnapshot::TYPE_SALES])
            ->assertRedirect(route('erp.reports.index'));

        $snapshot = ReportSnapshot::where('type', ReportSnapshot::TYPE_SALES)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(ReportSnapshot::STATUS_COMPLETED, $snapshot->status);
        $this->assertNull($snapshot->error);

        $payload = $snapshot->payload;

        // Six completed seeded orders totaling 1986 (202+91+525+670+155+343).
        $this->assertEquals(1986, $payload['summary']['total_revenue']);
        $this->assertSame(6, $payload['summary']['order_count']);
        $this->assertEquals(331, $payload['summary']['average_order_value']);
        // Every completed order is invoiced; the pending order is not.
        $this->assertSame(6, $payload['summary']['invoice_count']);

        // Top product is the Office Chair (630 = 2x210 + 1x210).
        $this->assertSame('Office Chair', $payload['top_products'][0]['name']);
        $this->assertEquals(630, $payload['top_products'][0]['revenue']);
        $this->assertSame(3, $payload['top_products'][0]['quantity']);

        // Top customer Paper & Co. Supplies (670 = the 2-months-ago order).
        $this->assertSame('Paper & Co. Supplies', $payload['top_customers'][0]['name']);
        $this->assertEquals(670, $payload['top_customers'][0]['revenue']);
    }

    public function test_running_the_inventory_report_stores_the_computed_payload(): void
    {
        config(['queue.default' => 'sync']);

        $this->actingAs($this->staff())
            ->post('/erp/reports', ['type' => ReportSnapshot::TYPE_INVENTORY])
            ->assertRedirect(route('erp.reports.index'));

        $snapshot = ReportSnapshot::where('type', ReportSnapshot::TYPE_INVENTORY)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(ReportSnapshot::STATUS_COMPLETED, $snapshot->status);

        $payload = $snapshot->payload;

        // 12 seeded products; total valuation = stock x cost across the
        // catalog after the seeded sales and the TechSource receipt (4656).
        $this->assertSame(12, $payload['summary']['product_count']);
        $this->assertEquals(4656, $payload['summary']['total_valuation']);

        // Monitor, A4 Paper, Coffee Beans and Packing Tape are below threshold.
        $this->assertSame(4, $payload['summary']['low_stock_count']);
        $this->assertSame('Beverages', $payload['valuation_by_category'][0]['category']);
        $this->assertEquals(248, $payload['valuation_by_category'][0]['valuation']);

        // 12 opening + 3 receipt lines "in"; 23 sale lines "out"; no adjustments.
        $this->assertSame(15, $payload['movements']['in']);
        $this->assertSame(23, $payload['movements']['out']);
        $this->assertSame(0, $payload['movements']['adjustment']);
    }

    public function test_a_failed_generation_marks_the_snapshot_failed_with_the_error(): void
    {
        config(['queue.default' => 'sync']);

        $user = $this->staff();
        $snapshot = ReportSnapshot::create([
            'type' => 'unknown',
            'title' => 'Broken report',
            'parameters' => [],
            'status' => ReportSnapshot::STATUS_PENDING,
            'generated_by' => $user->id,
        ]);

        GenerateReport::dispatch($snapshot->id);

        $snapshot->refresh();

        $this->assertSame(ReportSnapshot::STATUS_FAILED, $snapshot->status);
        $this->assertStringContainsString(
            'Unsupported report type [unknown]',
            $snapshot->error,
        );
    }
}
