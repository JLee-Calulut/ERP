<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Invoice;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_dashboard_renders_kpi_props_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
                ->has('stats', 12)
                ->has('revenueTrend.labels', 6)
                ->has('recentActivity')
                ->has('quickActions', 4));
    }

    public function test_dashboard_kpis_reflect_the_seeded_baseline(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
                // 3 seeded demo users + the factory user, 3 core roles,
                // 13 permissions, 1 administrator.
                ->where('stats.users', 4)
                ->where('stats.roles', 3)
                ->where('stats.permissions', 13)
                ->where('stats.administrators', 1));
    }

    public function test_dashboard_kpis_reflect_the_seeded_inventory(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
                // InventorySeeder: 12 products, 4 of them at or below threshold.
                ->where('stats.products', 12)
                ->where('stats.lowStock', 4));
    }

    public function test_dashboard_kpis_reflect_seeded_sales_data(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
                ->where('stats.revenue', 1986)
                ->where('stats.orders', 7)
                ->where('stats.customers', 4)
                ->where('stats.products', 12)
                ->where('stats.openPurchaseOrders', 4)
                ->where('stats.pendingPurchaseValue', 1060)
                ->has('revenueTrend.labels', 6)
                ->has('revenueTrend.values', 6)
                ->where('revenueTrend.values.0', 202)
                ->where('revenueTrend.values.1', 91)
                ->where('revenueTrend.values.5', 343)
                ->has('recentActivity'));
    }

    public function test_dashboard_kpis_reflect_seeded_sales_and_purchasing_data(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
                ->where('stats.revenue', 1986)
                ->where('stats.orders', 7)
                ->where('stats.customers', 4)
                ->where('stats.products', 12)
                ->where('stats.openPurchaseOrders', 4)
                ->where('stats.pendingPurchaseValue', 1060)
                ->has('revenueTrend.labels', 6)
                ->has('revenueTrend.values', 6));
    }

    public function test_dashboard_quick_actions_are_available_to_an_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
                ->where('quickActions.0.label', 'Manage users')
                ->where('quickActions.0.available', true)
                ->where('quickActions.0.href', '/erp/users')
                ->where('quickActions.1.label', 'Roles & permissions')
                ->where('quickActions.1.available', true)
                ->where('quickActions.1.href', '/erp/roles'));
    }

    public function test_dashboard_quick_action_create_sale_is_visible_for_customers_managers(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($manager)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
                ->has('quickActions', 4)
                ->where('quickActions.0.label', 'Create sale')
                ->where('quickActions.0.available', true)
                ->where('quickActions.0.href', '/erp/sales/orders/create'));
    }

    public function test_dashboard_quick_actions_are_unavailable_to_staff(): void
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        $this->actingAs($user)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
                ->where('quickActions.0.available', false)
                ->where('quickActions.0.href', null)
                ->where('quickActions.1.available', false)
                ->where('quickActions.1.href', null)
                ->where('quickActions.2.available', false));
    }

    public function test_dashboard_quick_action_create_purchase_order_is_visible_for_managers(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($manager)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
                ->where('quickActions.0.label', 'Create sale')
                ->where('quickActions.2.label', 'Create purchase order')
                ->where('quickActions.2.available', true)
                ->where('quickActions.2.href', '/erp/purchasing/orders/create'));
    }

    public function test_dashboard_counts_roles_and_permissions_added_later(): void
    {
        $role = Role::create(['name' => 'Warehouse Clerk', 'slug' => 'warehouse-clerk', 'description' => null]);
        $role->permissions()->attach(Permission::where('slug', 'inventory.view')->first());

        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
                ->where('stats.roles', 4)
                ->where('stats.permissions', 13));
    }

    public function test_dashboard_exposes_the_accounting_kpi_from_live_ledger_balances(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
                ->has('stats', 12)
                // Seeded ledger: 12 sample entries plus the two most recent
                // invoices (INV-000005 $155, INV-000006 $343) and the
                // TechSource receipt ($1,140) posted to accounting. Revenue
                // balances $13,798 against $19,350 of expenses = −$5,552.
                ->where('stats.netIncome', -5552)
                ->has('recentActivity'));
    }

    public function test_dashboard_feed_includes_accounting_posting_activity(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
                // Posting activity is the newest history in the feed: the
                // two posted invoices, the posted receipt and the standalone
                // journal postings lead the list on a fresh seed.
                ->has('recentActivity'))
            // Assert on the rendered feed messages rather than ids/positions —
            // auto_increment carries over between tests, so document ids and
            // the JE- numbers are not stable across a sequential run.
            ->assertSee('posted to accounting', false)
            ->assertSee('Journal entry JE-', false);
    }

    public function test_posting_an_invoice_moves_the_net_income_kpi(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        // INV-000001 ($202, the oldest seeded invoice) is still unposted.
        // Resolve it dynamically: auto_increment carries over between tests,
        // so the seeded rows are not guaranteed to start at id 1.
        $invoice = Invoice::first();

        $this->actingAs($user)
            ->post("/erp/sales/invoices/{$invoice->id}/post")
            ->assertRedirect();

        $this->actingAs($user)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
                ->where('stats.netIncome', -5350));
    }

    public function test_dashboard_feed_includes_reporting_activity_latest_first(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
                // Reporting activity leads the feed on a fresh seed (every
                // seed row shares the same second and the report runs are the
                // newest module history): the regenerated sales run first,
                // then the five first-run reports in id order.
                ->where('recentActivity.0.message', 'Regenerated Sales Report')
                ->where('recentActivity.1.message', fn (string $message) => str_starts_with($message, 'Purchasing Report #') && str_ends_with($message, ' generated'))
                ->where('recentActivity.2.message', fn (string $message) => str_starts_with($message, 'Balance Sheet #') && str_ends_with($message, ' generated'))
                ->where('recentActivity.3.message', fn (string $message) => str_starts_with($message, 'Income Statement #') && str_ends_with($message, ' generated'))
                ->where('recentActivity.4.message', fn (string $message) => str_starts_with($message, 'Inventory Report #') && str_ends_with($message, ' generated'))
                ->where('recentActivity.5.message', fn (string $message) => str_starts_with($message, 'Sales Report #') && str_ends_with($message, ' generated'))
                // One feed entry per snapshot — the regenerated run appears
                // exactly once, alongside the first runs.
                ->where('recentActivity.0.icon', '📊'))
            ->assertSee('Regenerated Sales Report', false);
    }

    public function test_running_a_report_adds_a_new_snapshot_entry_at_the_top_of_the_feed(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        // Running a report through the same pipeline as a regeneration yields
        // a fresh completed snapshot; because a balance-sheet run already
        // exists on the seed, the new run is that type's second completion,
        // surfaced as a regenerated entry leading the feed.
        $this->actingAs($manager)
            ->post('/erp/reports', ['type' => 'balance_sheet'])
            ->assertRedirect();

        $this->actingAs($manager)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
                ->where('recentActivity.0.message', 'Regenerated Balance Sheet')
                ->where('recentActivity.0.verb', 'regenerated')
                ->where('recentActivity.0.icon', '📊'));
    }

    public function test_dashboard_activity_feed_includes_sales_and_stock_history(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertSee('Sales order #', false)
            ->assertSee('Stock received', false);
    }

    public function test_dashboard_activity_feed_includes_purchasing_history(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertSee('Sales order #', false)
            ->assertSee('Stock received', false)
            ->assertSee('received from', false);
    }
}
