<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\ReportSnapshot;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\ReportingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialStatementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        return $user;
    }

    private function reporting(): ReportingService
    {
        return app(ReportingService::class);
    }

    private function accounting(): AccountingService
    {
        return app(AccountingService::class);
    }

    private function financialTypes(): array
    {
        return [
            ReportSnapshot::TYPE_INCOME_STATEMENT,
            ReportSnapshot::TYPE_BALANCE_SHEET,
            ReportSnapshot::TYPE_PURCHASING,
        ];
    }

    // ----------------------------------------------------------------------
    // Authorization
    // ----------------------------------------------------------------------

    public function test_the_financial_statement_library_requires_authentication(): void
    {
        $this->get('/erp/reports')
            ->assertRedirect(route('erp.login'));
    }

    public function test_the_new_report_types_require_the_reports_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/erp/reports')
            ->assertForbidden();

        foreach ($this->financialTypes() as $type) {
            $this->actingAs($user)
                ->post('/erp/reports', ['type' => $type])
                ->assertForbidden();
        }
    }

    public function test_a_role_without_reports_view_cannot_run_the_new_statements(): void
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
            ->post('/erp/reports', ['type' => ReportSnapshot::TYPE_INCOME_STATEMENT])
            ->assertForbidden();
    }

    // ----------------------------------------------------------------------
    // Income statement
    // ----------------------------------------------------------------------

    public function test_the_income_statement_nets_to_the_seeded_net_income(): void
    {
        $payload = $this->reporting()->incomeStatement();

        $this->assertEquals(13798, $payload['summary']['total_revenue']);
        $this->assertEquals(19350, $payload['summary']['total_expenses']);

        // Reconciles with the dashboard Net Income figure and the same
        // live-derivation AccountingService::netIncome() uses (never stored).
        $this->assertEquals(-5552, $payload['summary']['net_income']);
        $this->assertEquals(
            $this->accounting()->netIncome(),
            $payload['summary']['net_income'],
        );
        $this->assertEquals(
            $payload['summary']['total_revenue'] - $payload['summary']['total_expenses'],
            $payload['summary']['net_income'],
        );
    }

    public function test_staff_with_reports_view_can_open_the_seeded_income_statement(): void
    {
        $snapshot = ReportSnapshot::where('type', ReportSnapshot::TYPE_INCOME_STATEMENT)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->actingAs($this->staff())
            ->get("/erp/reports/{$snapshot->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Reporting/IncomeStatement')
                ->where('snapshot.id', $snapshot->id)
                ->where('snapshot.payload.summary.net_income', -5552)
                ->where('snapshot.payload.summary.total_revenue', 13798)
                ->where('snapshot.payload.summary.total_expenses', 19350));
    }

    // ----------------------------------------------------------------------
    // Balance sheet
    // ----------------------------------------------------------------------

    public function test_the_balance_sheet_balances_and_matches_the_trial_balance(): void
    {
        $payload = $this->reporting()->balanceSheet();
        $totals = $payload['totals'];
        $trial = $this->accounting()->trialBalance();

        // The seeded ledger: assets = liabilities + equity. Retained earnings
        // roll up the same net income (-5552) the income statement derives.
        $this->assertTrue($totals['balanced']);
        $this->assertEquals($totals['assets'], $totals['liabilities_and_equity']);
        $this->assertEquals(-5552, $payload['retained_earnings']);

        $this->assertEquals(70788, $totals['assets']);
        $this->assertEquals(26340, $totals['liabilities']);
        $this->assertEquals(44448, $totals['equity']);

        // Matches the trial-balance figures: the ledger totals $125,288 per
        // side on the seeded entries, and the sheet reports the same.
        $this->assertEquals(125288, $trial['totals']['debit']);
        $this->assertEquals(125288, $trial['totals']['credit']);
        $this->assertEquals($trial['totals']['debit'], $payload['ledger']['total_debits']);
        $this->assertEquals($trial['totals']['credit'], $payload['ledger']['total_credits']);

        // The asset total equals the sum of the (debit-normal) asset balances
        // the trial balance derives from the same journal lines.
        $trialAssets = collect($trial['accounts'])
            ->where('type', 'asset')
            ->sum(fn (array $account) => -$account['balance']);
        $this->assertEquals(round($trialAssets, 2), $totals['assets']);
    }

    public function test_staff_with_reports_view_can_open_the_seeded_balance_sheet(): void
    {
        $snapshot = ReportSnapshot::where('type', ReportSnapshot::TYPE_BALANCE_SHEET)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->actingAs($this->staff())
            ->get("/erp/reports/{$snapshot->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Reporting/BalanceSheet')
                ->where('snapshot.id', $snapshot->id)
                ->where('snapshot.payload.totals.balanced', true)
                ->where('snapshot.payload.totals.assets', 70788)
                ->where('snapshot.payload.totals.liabilities_and_equity', 70788));
    }

    // ----------------------------------------------------------------------
    // Purchasing report
    // ----------------------------------------------------------------------

    public function test_the_purchasing_report_matches_the_seeded_orders_and_receipts(): void
    {
        $payload = $this->reporting()->purchasing();

        // Spend is the received value (the single seeded receipt, $1,140);
        // pending mirrors the dashboard "Pending PO Value" KPI (the two
        // confirmed, not-yet-received orders totaling $1,060).
        $this->assertEquals(1140, $payload['summary']['total_spend']);
        $this->assertEquals(1140, $payload['summary']['received_value']);
        $this->assertEquals(1060, $payload['summary']['pending_value']);
        $this->assertSame(1, $payload['summary']['receipt_count']);
        $this->assertSame(5, $payload['summary']['order_count']);

        $this->assertEquals([
            'received' => 1140,
            'pending' => 1060,
        ], $payload['received_vs_pending']);

        // PO value by status: the received TechSource order ($1,140), the two
        // confirmed orders ($370 + $690), and the two drafts ($220 + $220).
        $byStatus = collect($payload['po_value_by_status'])->keyBy('status');
        $this->assertEquals(1140, $byStatus['received']['value']);
        $this->assertSame(1, $byStatus['received']['count']);
        $this->assertEquals(1060, $byStatus['confirmed']['value']);
        $this->assertSame(2, $byStatus['confirmed']['count']);
        $this->assertEquals(440, $byStatus['draft']['value']);
        $this->assertSame(2, $byStatus['draft']['count']);

        // Spend by supplier: only TechSource Distribution has received stock.
        $techSource = collect($payload['spend_by_supplier'])
            ->firstWhere('supplier', 'TechSource Distribution');
        $this->assertEquals(1140, $techSource['received_value']);
        $this->assertSame(1, $techSource['order_count']);
    }

    public function test_staff_with_reports_view_can_open_the_seeded_purchasing_report(): void
    {
        $snapshot = ReportSnapshot::where('type', ReportSnapshot::TYPE_PURCHASING)
            ->orderByDesc('id')
            ->firstOrFail();

        $this->actingAs($this->staff())
            ->get("/erp/reports/{$snapshot->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Reporting/Purchasing')
                ->where('snapshot.id', $snapshot->id)
                ->where('snapshot.payload.summary.total_spend', 1140)
                ->where('snapshot.payload.summary.pending_value', 1060));
    }

    // ----------------------------------------------------------------------
    // Pipeline through the queued job
    // ----------------------------------------------------------------------

    public function test_running_the_financial_statement_reports_completes_through_the_job(): void
    {
        config(['queue.default' => 'sync']);

        foreach ($this->financialTypes() as $type) {
            $this->actingAs($this->staff())
                ->post('/erp/reports', ['type' => $type])
                ->assertRedirect(route('erp.reports.index'));

            $snapshot = ReportSnapshot::where('type', $type)
                ->latest('id')
                ->firstOrFail();

            $this->assertSame(ReportSnapshot::STATUS_COMPLETED, $snapshot->status);
            $this->assertNull($snapshot->error);
            $this->assertIsArray($snapshot->payload);

            if ($type === ReportSnapshot::TYPE_INCOME_STATEMENT) {
                $this->assertEquals(-5552, $snapshot->payload['summary']['net_income']);
            }
            if ($type === ReportSnapshot::TYPE_BALANCE_SHEET) {
                $this->assertTrue($snapshot->payload['totals']['balanced']);
            }
            if ($type === ReportSnapshot::TYPE_PURCHASING) {
                $this->assertEquals(1060, $snapshot->payload['summary']['pending_value']);
            }
        }
    }

    // ----------------------------------------------------------------------
    // Date-range reconciliation
    // ----------------------------------------------------------------------

    public function test_the_statements_still_reconcile_within_a_date_range(): void
    {
        $parameters = [
            'date_from' => now()->subMonths(2)->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
        ];

        $income = $this->reporting()->incomeStatement($parameters);
        $this->assertEquals(
            round(
                $income['summary']['total_revenue'] - $income['summary']['total_expenses'],
                2,
            ),
            $income['summary']['net_income'],
        );

        $sheet = $this->reporting()->balanceSheet($parameters);
        $this->assertTrue($sheet['totals']['balanced']);
        $this->assertEquals(
            $sheet['totals']['assets'],
            $sheet['totals']['liabilities_and_equity'],
        );
        $this->assertEquals(
            $sheet['totals']['assets'],
            round($sheet['totals']['liabilities'] + $sheet['totals']['equity'], 2),
        );
    }
}
