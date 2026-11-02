<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryStockTest extends TestCase
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

    private function productWithStock(int $quantity): Product
    {
        return Product::create([
            'name' => 'Tracked Item',
            'sku' => 'STOCK-'.fake()->unique()->numberBetween(100, 9999),
            'cost_price' => 1,
            'selling_price' => 2,
            'stock_quantity' => $quantity,
            'low_stock_threshold' => 5,
        ]);
    }

    public function test_stock_requires_authentication(): void
    {
        $this->get('/erp/inventory/stock')->assertRedirect(route('erp.login'));
    }

    public function test_stock_requires_the_inventory_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/erp/inventory/stock')
            ->assertForbidden();
    }

    public function test_staff_can_view_the_stock_index(): void
    {
        $this->actingAs($this->staff())
            ->get('/erp/inventory/stock')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Inventory/Stock/Index')
                ->has('products', 12)
                // 12 opening movements + the movements recorded by SalesSeeder
                // + the seed receipt booked for the TechSource purchase order.
                ->has('transactions', 12)
                ->has('types', 3));
    }

    public function test_staff_cannot_record_a_movement(): void
    {
        $product = $this->productWithStock(10);

        $this->actingAs($this->staff())
            ->post('/erp/inventory/stock', [
                'product_id' => $product->id,
                'type' => 'in',
                'quantity' => 5,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_receive_stock(): void
    {
        $admin = $this->admin();
        $product = $this->productWithStock(0);

        $this->actingAs($admin)
            ->post('/erp/inventory/stock', [
                'product_id' => $product->id,
                'type' => 'in',
                'quantity' => 10,
                'reason' => 'Delivery from supplier',
            ])
            ->assertRedirect(route('erp.inventory.stock.index'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 10]);
        $this->assertDatabaseHas('stock_transactions', [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 10,
            'previous_quantity' => 0,
            'new_quantity' => 10,
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_issue_stock(): void
    {
        $admin = $this->admin();
        $product = $this->productWithStock(20);

        $this->actingAs($admin)
            ->post('/erp/inventory/stock', [
                'product_id' => $product->id,
                'type' => 'out',
                'quantity' => 5,
            ])
            ->assertRedirect(route('erp.inventory.stock.index'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 15]);
        $this->assertDatabaseHas('stock_transactions', [
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => -5,
            'new_quantity' => 15,
        ]);
    }

    public function test_a_movement_cannot_drive_stock_below_zero(): void
    {
        $admin = $this->admin();
        $product = $this->productWithStock(5);

        $this->actingAs($admin)
            ->post('/erp/inventory/stock', [
                'product_id' => $product->id,
                'type' => 'out',
                'quantity' => 10,
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 5]);
        $this->assertDatabaseMissing('stock_transactions', ['product_id' => $product->id]);
    }

    public function test_admin_can_adjust_stock_to_a_target_level(): void
    {
        $admin = $this->admin();
        $product = $this->productWithStock(8);

        $this->actingAs($admin)
            ->post('/erp/inventory/stock', [
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => 3,
                'reason' => 'Cycle count correction',
            ])
            ->assertRedirect(route('erp.inventory.stock.index'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 3]);
        $this->assertDatabaseHas('stock_transactions', [
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => -5,
            'previous_quantity' => 8,
            'new_quantity' => 3,
        ]);

        // A cycle count may also zero out a product.
        $this->actingAs($admin)
            ->post('/erp/inventory/stock', [
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => 0,
            ]);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 0]);
    }

    public function test_a_movement_requires_product_type_and_quantity(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/erp/inventory/stock', ['type' => 'in', 'quantity' => 5])
            ->assertSessionHasErrors('product_id');

        $this->actingAs($admin)
            ->post('/erp/inventory/stock', ['product_id' => 1, 'quantity' => 5])
            ->assertSessionHasErrors('type');

        $this->actingAs($admin)
            ->post('/erp/inventory/stock', ['product_id' => 1, 'type' => 'in'])
            ->assertSessionHasErrors('quantity');
    }

    public function test_admin_can_view_the_movement_form(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/inventory/stock/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Inventory/Stock/Form')
                ->has('products', 12)
                ->has('types', 3));
    }

    public function test_dashboard_kpis_reflect_inventory_data(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Dashboard')
                ->where('stats.products', 12)
                ->whereType('stats.lowStock', 'integer'));
    }
}
