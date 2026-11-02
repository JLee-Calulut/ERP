<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryProductsTest extends TestCase
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

    public function test_products_require_authentication(): void
    {
        $this->get('/erp/inventory/products')->assertRedirect(route('erp.login'));
    }

    public function test_products_require_the_inventory_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/erp/inventory/products')
            ->assertForbidden();
    }

    public function test_staff_can_view_the_products_index(): void
    {
        $this->actingAs($this->staff())
            ->get('/erp/inventory/products')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Inventory/Products/Index')
                ->has('products', 12)
                ->has('categories', 5)
                ->has('filters'));
    }

    public function test_products_creation_requires_the_inventory_manage_permission(): void
    {
        $this->actingAs($this->staff())
            ->post('/erp/inventory/products', [
                'name' => 'Blocked Product',
                'sku' => 'BLOCKED-001',
                'cost_price' => 1,
                'selling_price' => 2,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_view_the_create_product_form(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/inventory/products/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Inventory/Products/Form')
                ->where('product', null)
                ->has('categories', 5));
    }

    public function test_admin_can_create_a_product(): void
    {
        $category = Category::first();

        $this->actingAs($this->admin())
            ->post('/erp/inventory/products', [
                'name' => 'USB-C Cable',
                'sku' => 'CABLE-USB-C-013',
                'category_id' => $category->id,
                'description' => 'Fast charging cable.',
                'cost_price' => 3.5,
                'selling_price' => 8,
                'stock_quantity' => 25,
                'low_stock_threshold' => 5,
                'is_active' => true,
            ])
            ->assertRedirect(route('erp.inventory.products.index'));

        $this->assertDatabaseHas('products', [
            'sku' => 'CABLE-USB-C-013',
            'name' => 'USB-C Cable',
            'category_id' => $category->id,
            'stock_quantity' => 25,
        ]);
    }

    public function test_product_sku_is_required_and_unique(): void
    {
        Product::create([
            'name' => 'Existing Item',
            'sku' => 'EXISTING-001',
            'cost_price' => 1,
            'selling_price' => 2,
        ]);

        $this->actingAs($this->admin())
            ->post('/erp/inventory/products', [
                'name' => 'No Sku',
                'sku' => '',
                'cost_price' => 1,
                'selling_price' => 2,
            ])
            ->assertSessionHasErrors('sku');

        $this->actingAs($this->admin())
            ->post('/erp/inventory/products', [
                'name' => 'Duplicate Sku',
                'sku' => 'EXISTING-001',
                'cost_price' => 1,
                'selling_price' => 2,
            ])
            ->assertSessionHasErrors('sku');
    }

    public function test_admin_can_edit_a_product(): void
    {
        $admin = $this->admin();
        $product = Product::create([
            'name' => 'Old Name',
            'sku' => 'EDIT-001',
            'cost_price' => 1,
            'selling_price' => 2,
            'stock_quantity' => 4,
            'low_stock_threshold' => 5,
        ]);

        $this->actingAs($admin)
            ->put("/erp/inventory/products/{$product->id}", [
                'name' => 'New Name',
                'sku' => 'EDIT-001',
                'cost_price' => 2,
                'selling_price' => 5,
                'stock_quantity' => 10,
                'low_stock_threshold' => 3,
                'is_active' => true,
            ])
            ->assertRedirect(route('erp.inventory.products.index'));

        $product->refresh();
        $this->assertEquals('New Name', $product->name);
        $this->assertEquals(10, $product->stock_quantity);
        $this->assertEquals(3, $product->low_stock_threshold);
    }

    public function test_product_edit_form_exposes_categories(): void
    {
        $product = Product::first();

        $this->actingAs($this->admin())
            ->get("/erp/inventory/products/{$product->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Inventory/Products/Form')
                ->where('product.id', $product->id)
                ->has('categories', 5));
    }

    public function test_admin_can_delete_a_product(): void
    {
        $admin = $this->admin();
        $product = Product::create([
            'name' => 'Discontinued Item',
            'sku' => 'GONE-001',
            'cost_price' => 1,
            'selling_price' => 2,
        ]);

        $this->actingAs($admin)
            ->delete("/erp/inventory/products/{$product->id}")
            ->assertRedirect(route('erp.inventory.products.index'));

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }
}
