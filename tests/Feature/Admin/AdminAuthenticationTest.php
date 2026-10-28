<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_login_screen_can_be_rendered(): void
    {
        $this->get('/erp/login')->assertOk();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/erp/dashboard')->assertRedirect(route('erp.login'));
    }

    public function test_admin_users_can_authenticate_through_the_admin_login(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->post('/erp/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('erp.dashboard'));
    }

    public function test_authenticated_users_can_open_the_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        $this->actingAs($user)->get('/erp/dashboard')->assertOk();
    }

    public function test_authenticated_users_are_redirected_away_from_the_admin_login(): void
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        $this->actingAs($user)->get('/erp/login')
            ->assertRedirect(route('erp.dashboard'));
    }

    public function test_admin_users_can_logout(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->post('/erp/logout');

        $this->assertGuest();
        $response->assertRedirect(route('erp.login'));
    }
}
