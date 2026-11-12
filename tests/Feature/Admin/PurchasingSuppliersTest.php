<?php

namespace Tests\Feature\Admin;

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchasingSuppliersTest extends TestCase
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

    public function test_suppliers_require_authentication(): void
    {
        $this->get('/erp/purchasing/suppliers')->assertRedirect(route('erp.login'));
    }

    public function test_suppliers_require_the_purchasing_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/erp/purchasing/suppliers')
            ->assertForbidden();
    }

    public function test_staff_can_view_the_suppliers_index(): void
    {
        $this->actingAs($this->staff())
            ->get('/erp/purchasing/suppliers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Purchasing/Suppliers/Index')
                ->has('suppliers', 5)
                ->has('filters'));
    }

    public function test_supplier_mutations_require_the_purchasing_manage_permission(): void
    {
        $this->actingAs($this->staff())
            ->post('/erp/purchasing/suppliers', ['name' => 'Blocked Supplier'])
            ->assertForbidden();

        $existing = Supplier::first();

        $this->actingAs($this->staff())
            ->put("/erp/purchasing/suppliers/{$existing->id}", ['name' => 'Blocked Update'])
            ->assertForbidden();

        $this->actingAs($this->staff())
            ->delete("/erp/purchasing/suppliers/{$existing->id}")
            ->assertForbidden();
    }

    public function test_admin_can_view_the_create_supplier_form(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/purchasing/suppliers/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Purchasing/Suppliers/Form')
                ->where('supplier', null));
    }

    public function test_admin_can_create_a_supplier(): void
    {
        $this->actingAs($this->admin())
            ->post('/erp/purchasing/suppliers', [
                'name' => 'Acme Components',
                'email' => 'sales@acme-components.example',
                'phone' => '+1 555-0299',
                'address_line1' => '1200 Supplier Road',
                'address_city' => 'Detroit',
                'address_state' => 'MI',
                'address_postal_code' => '48201',
                'address_country' => 'United States',
            ])
            ->assertRedirect(route('erp.purchasing.suppliers.index'));

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Acme Components',
            'email' => 'sales@acme-components.example',
            'address_city' => 'Detroit',
        ]);
    }

    public function test_a_supplier_name_is_required_and_email_is_unique(): void
    {
        $this->actingAs($this->admin())
            ->post('/erp/purchasing/suppliers', ['name' => ''])
            ->assertSessionHasErrors('name');

        $email = 'taken@example.test';
        Supplier::create(['name' => 'Original', 'email' => $email]);

        $this->actingAs($this->admin())
            ->post('/erp/purchasing/suppliers', ['name' => 'Duplicate', 'email' => $email])
            ->assertSessionHasErrors('email');
    }

    public function test_admin_can_edit_a_supplier(): void
    {
        $admin = $this->admin();
        $supplier = Supplier::create(['name' => 'Old Name']);

        $this->actingAs($admin)
            ->put("/erp/purchasing/suppliers/{$supplier->id}", [
                'name' => 'New Name',
                'email' => 'new@example.test',
            ])
            ->assertRedirect(route('erp.purchasing.suppliers.index'));

        $supplier->refresh();
        $this->assertEquals('New Name', $supplier->name);
        $this->assertEquals('new@example.test', $supplier->email);
    }

    public function test_admin_can_delete_a_supplier(): void
    {
        $admin = $this->admin();
        $supplier = Supplier::create(['name' => 'Gone Supplier']);

        $this->actingAs($admin)
            ->delete("/erp/purchasing/suppliers/{$supplier->id}")
            ->assertRedirect(route('erp.purchasing.suppliers.index'));

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }
}
