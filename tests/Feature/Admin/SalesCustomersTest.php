<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesCustomersTest extends TestCase
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

    public function test_customers_require_authentication(): void
    {
        $this->get('/erp/sales/customers')->assertRedirect(route('erp.login'));
    }

    public function test_customers_require_the_sales_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/erp/sales/customers')
            ->assertForbidden();
    }

    public function test_staff_can_view_the_customers_index(): void
    {
        $this->actingAs($this->staff())
            ->get('/erp/sales/customers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Sales/Customers/Index')
                ->has('customers', 4)
                ->has('filters'));
    }

    public function test_customers_mutations_require_the_sales_manage_permission(): void
    {
        $this->actingAs($this->staff())
            ->post('/erp/sales/customers', ['name' => 'Blocked Customer'])
            ->assertForbidden();

        $existing = Customer::first();

        $this->actingAs($this->staff())
            ->put("/erp/sales/customers/{$existing->id}", ['name' => 'Blocked Update'])
            ->assertForbidden();

        $this->actingAs($this->staff())
            ->delete("/erp/sales/customers/{$existing->id}")
            ->assertForbidden();
    }

    public function test_admin_can_view_the_create_customer_form(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/sales/customers/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Sales/Customers/Form')
                ->where('customer', null));
    }

    public function test_admin_can_create_a_customer(): void
    {
        $this->actingAs($this->admin())
            ->post('/erp/sales/customers', [
                'name' => 'Acme Widgets',
                'email' => 'billing@acme.example',
                'phone' => '+1 555-0199',
                'address_line1' => '9 Factory Row',
                'address_city' => 'Detroit',
                'address_state' => 'MI',
                'address_postal_code' => '48201',
                'address_country' => 'United States',
            ])
            ->assertRedirect(route('erp.sales.customers.index'));

        $this->assertDatabaseHas('customers', [
            'name' => 'Acme Widgets',
            'email' => 'billing@acme.example',
            'address_city' => 'Detroit',
        ]);
    }

    public function test_a_customer_name_is_required_and_email_is_unique(): void
    {
        $this->actingAs($this->admin())
            ->post('/erp/sales/customers', ['name' => ''])
            ->assertSessionHasErrors('name');

        $email = 'taken@example.test';
        Customer::create(['name' => 'Original', 'email' => $email]);

        $this->actingAs($this->admin())
            ->post('/erp/sales/customers', ['name' => 'Duplicate', 'email' => $email])
            ->assertSessionHasErrors('email');
    }

    public function test_admin_can_edit_a_customer(): void
    {
        $admin = $this->admin();
        $customer = Customer::create(['name' => 'Old Name']);

        $this->actingAs($admin)
            ->put("/erp/sales/customers/{$customer->id}", [
                'name' => 'New Name',
                'email' => 'new@example.test',
            ])
            ->assertRedirect(route('erp.sales.customers.index'));

        $customer->refresh();
        $this->assertEquals('New Name', $customer->name);
        $this->assertEquals('new@example.test', $customer->email);
    }

    public function test_admin_can_delete_a_customer(): void
    {
        $admin = $this->admin();
        $customer = Customer::create(['name' => 'Gone Customer']);

        $this->actingAs($admin)
            ->delete("/erp/sales/customers/{$customer->id}")
            ->assertRedirect(route('erp.sales.customers.index'));

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }
}
