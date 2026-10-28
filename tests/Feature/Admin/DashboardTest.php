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
                ->has('stats', 4)
                ->has('quickActions', 2));
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
                ->where('quickActions.1.href', null));
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
}
