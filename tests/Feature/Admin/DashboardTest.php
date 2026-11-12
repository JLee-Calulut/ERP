<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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
                ->has('stats', 11)
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
                ->has('revenueTrend.labels', 6)
                ->has('revenueTrend.values', 6));
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

    public function test_dashboard_quick_action_create_sale_is_visible_for_managers(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($manager)
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
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
