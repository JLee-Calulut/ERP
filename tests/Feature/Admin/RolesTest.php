<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RolesTest extends TestCase
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

    public function test_roles_require_authentication(): void
    {
        $this->get('/erp/roles')->assertRedirect(route('erp.login'));
    }

    public function test_roles_require_the_roles_manage_permission(): void
    {
        $this->actingAs($this->staff())
            ->get('/erp/roles')
            ->assertForbidden();
    }

    public function test_admin_can_view_the_roles_index(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/roles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Roles/Index')
                ->has('roles', 3)
                ->has('permissionCount'));
    }

    public function test_admin_can_view_the_create_role_form(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/roles/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Roles/Form')
                ->where('role', null)
                ->has('permissionGroups'));
    }

    public function test_admin_can_create_a_role_with_permissions(): void
    {
        $inventoryView = Permission::where('slug', 'inventory.view')->first();
        $salesView = Permission::where('slug', 'sales.view')->first();

        $this->actingAs($this->admin())
            ->post('/erp/roles', [
                'name' => 'Warehouse Clerk',
                'description' => 'Handles stock.',
                'permissions' => [$inventoryView->id, $salesView->id],
            ])
            ->assertRedirect(route('erp.roles.index'));

        $role = Role::where('slug', 'warehouse-clerk')->first();
        $this->assertNotNull($role);
        $this->assertEquals('Warehouse Clerk', $role->name);
        $this->assertSame([$inventoryView->id, $salesView->id], $role->permissions()->pluck('permissions.id')->all());
    }

    public function test_role_name_is_required_and_unique(): void
    {
        $this->actingAs($this->admin())
            ->post('/erp/roles', ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin())
            ->post('/erp/roles', ['name' => 'Admin'])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_edit_a_role_and_sync_permissions(): void
    {
        $admin = $this->admin();
        $role = Role::create(['name' => 'Support Agent', 'slug' => 'support-agent', 'description' => null]);
        $newPermission = Permission::where('slug', 'reports.view')->first();

        $this->actingAs($admin)
            ->put("/erp/roles/{$role->id}", [
                'name' => 'Support Lead',
                'permissions' => [$newPermission->id],
            ])
            ->assertRedirect(route('erp.roles.index'));

        $role->refresh();
        $this->assertEquals('Support Lead', $role->name);
        $this->assertSame([$newPermission->id], $role->permissions()->pluck('permissions.id')->all());
    }

    public function test_core_roles_cannot_be_deleted(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();

        $this->actingAs($this->admin())
            ->delete("/erp/roles/{$adminRole->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $adminRole->id]);
    }

    public function test_roles_in_use_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'Temp Role', 'slug' => 'temp-role', 'description' => null]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($this->admin())
            ->delete("/erp/roles/{$role->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_admin_can_delete_a_custom_role(): void
    {
        $role = Role::create(['name' => 'Temp Role', 'slug' => 'temp-role', 'description' => null]);

        $this->actingAs($this->admin())
            ->delete("/erp/roles/{$role->id}")
            ->assertRedirect(route('erp.roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
