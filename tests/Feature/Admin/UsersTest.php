<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UsersTest extends TestCase
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

    public function test_users_require_authentication(): void
    {
        $this->get('/erp/users')->assertRedirect(route('erp.login'));
    }

    public function test_users_require_the_users_manage_permission(): void
    {
        $this->actingAs($this->staff())
            ->get('/erp/users')
            ->assertForbidden();
    }

    public function test_admin_can_view_the_users_index(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Users/Index')
                ->has('users')
                ->has('roles', 3));
    }

    public function test_admin_can_view_the_create_user_form(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/users/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Users/Form')
                ->where('user', null)
                ->has('roles', 3));
    }

    public function test_admin_can_create_a_user_with_roles(): void
    {
        $manager = Role::where('slug', 'manager')->first();

        $this->actingAs($this->admin())
            ->post('/erp/users', [
                'name' => 'New Hire',
                'email' => 'newhire@erp.test',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
                'roles' => [$manager->id],
            ])
            ->assertRedirect(route('erp.users.index'));

        $user = User::where('email', 'newhire@erp.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('manager'));
    }

    public function test_create_user_requires_unique_email_and_min_password(): void
    {
        $this->actingAs($this->admin())
            ->post('/erp/users', [
                'name' => 'Dup',
                'email' => 'admin@erp.test',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
            ])
            ->assertSessionHasErrors('email');

        $this->actingAs($this->admin())
            ->post('/erp/users', [
                'name' => 'Short',
                'email' => 'short@erp.test',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_admin_can_update_a_user_and_sync_roles(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->assignRole('staff');
        $manager = Role::where('slug', 'manager')->first();

        $this->actingAs($admin)
            ->put("/erp/users/{$user->id}", [
                'name' => 'Updated Name',
                'email' => $user->email,
                'password' => '',
                'password_confirmation' => '',
                'roles' => [$manager->id],
            ])
            ->assertRedirect(route('erp.users.index'));

        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertTrue($user->hasRole('manager'));
        $this->assertFalse($user->hasRole('staff'));
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete("/erp/users/{$admin->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->delete("/erp/users/{$user->id}")
            ->assertRedirect(route('erp.users.index'));

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
