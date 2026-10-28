<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'New Staff Member',
            'email' => 'newstaff@erp.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'newstaff@erp.test']);
        $response->assertRedirect(route('erp.dashboard'));
    }

    public function test_new_users_start_with_the_staff_role(): void
    {
        $this->post('/register', [
            'name' => 'New Staff Member',
            'email' => 'newstaff@erp.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'newstaff@erp.test')->firstOrFail();

        $this->assertTrue($user->hasRole('staff'));
    }
}
