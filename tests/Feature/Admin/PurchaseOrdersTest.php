<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseOrdersTest extends TestCase
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

    private function product(int $stock, float $cost = 25): Product
    {
        return Product::create([
            'name' => 'Purchasing Test Item',
            'sku' => 'PUR-'.fake()->unique()->numberBetween(1000, 9999),
            'cost_price' => $cost,
            'selling_price' => $cost * 2,
            'stock_quantity' => $stock,
            'low_stock_threshold' => 5,
        ]);
    }

    private function supplier(): Supplier
    {
        return Supplier::first();
    }

    private function orderPayload(Product $product, int $quantity): array
    {
        return [
            'supplier_id' => $this->supplier()->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => $quantity],
            ],
        ];
    }

    public function test_purchase_orders_require_authentication(): void
    {
        $this->get('/erp/purchasing/orders')->assertRedirect(route('erp.login'));
    }

    public function test_purchase_orders_require_the_purchasing_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/erp/purchasing/orders')
            ->assertForbidden();
    }

    public function test_staff_can_view_the_orders_index(): void
    {
        $this->actingAs($this->staff())
            ->get('/erp/purchasing/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Purchasing/Orders/Index')
                ->has('purchaseOrders', 5)
                ->has('statuses', 4)
                ->has('filters'));
    }

    public function test_creating_an_order_requires_the_purchasing_manage_permission(): void
    {
        $product = $this->product(10);

        $this->actingAs($this->staff())
            ->post('/erp/purchasing/orders', $this->orderPayload($product, 1))
            ->assertForbidden();
    }

    public function test_admin_can_view_the_create_order_form(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/purchasing/orders/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Purchasing/Orders/Form')
                ->has('suppliers', 5)
                ->has('products', 12));
    }

    public function test_admin_can_create_an_order_priced_from_catalog_cost(): void
    {
        $admin = $this->admin();
        $product = $this->product(10, 25);

        $this->actingAs($admin)
            ->post('/erp/purchasing/orders', $this->orderPayload($product, 3))
            ->assertRedirect();

        $purchaseOrder = PurchaseOrder::latest('id')->first();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'supplier_id' => $this->supplier()->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'subtotal' => 75,
            'total' => 75,
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_cost' => 25,
            'line_total' => 75,
        ]);

        // A purchase order must never touch stock (receiving is Step 6 Part 2).
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 10]);
        // 35 from Inventory (12) + Sales (23) seeds; the seeded Part 2 receipt
        // adds 3 more "in" transactions for the TechSource order.
        $this->assertDatabaseCount('stock_transactions', 38);
    }

    public function test_totals_of_a_new_order_are_computed_from_catalog_costs(): void
    {
        $admin = $this->admin();
        $mouse = $this->product(10, 45);
        $keyboard = $this->product(10, 90);

        $this->actingAs($admin)
            ->post('/erp/purchasing/orders', [
                'supplier_id' => $this->supplier()->id,
                'items' => [
                    ['product_id' => $mouse->id, 'quantity' => 2],
                    ['product_id' => $keyboard->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $purchaseOrder = PurchaseOrder::latest('id')->first();

        $this->assertEquals(180.0, (float) $purchaseOrder->subtotal);
        $this->assertEquals(180.0, (float) $purchaseOrder->total);
        $this->assertCount(2, $purchaseOrder->items);
        $this->assertDatabaseHas('products', ['id' => $mouse->id, 'stock_quantity' => 10]);
    }

    public function test_an_order_requires_a_supplier_and_at_least_one_item(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/erp/purchasing/orders', ['items' => [['product_id' => 1, 'quantity' => 1]]])
            ->assertSessionHasErrors('supplier_id');

        $this->actingAs($admin)
            ->post('/erp/purchasing/orders', ['supplier_id' => $this->supplier()->id, 'items' => []])
            ->assertSessionHasErrors('items');
    }

    public function test_an_order_moves_through_the_status_flow(): void
    {
        $admin = $this->admin();
        $product = $this->product(10);
        $this->actingAs($admin)->post('/erp/purchasing/orders', $this->orderPayload($product, 1));

        $purchaseOrder = PurchaseOrder::latest('id')->first();

        $this->actingAs($admin)
            ->patch("/erp/purchasing/orders/{$purchaseOrder->id}/status", ['status' => 'confirmed'])
            ->assertRedirect(route('erp.purchasing.orders.show', $purchaseOrder));
        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'status' => 'confirmed']);

        $this->actingAs($admin)
            ->patch("/erp/purchasing/orders/{$purchaseOrder->id}/status", ['status' => 'cancelled'])
            ->assertRedirect(route('erp.purchasing.orders.show', $purchaseOrder));
        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'status' => 'cancelled']);
    }

    public function test_an_illegal_status_transition_is_rejected(): void
    {
        $admin = $this->admin();
        $product = $this->product(10);
        $this->actingAs($admin)->post('/erp/purchasing/orders', $this->orderPayload($product, 1));

        $purchaseOrder = PurchaseOrder::latest('id')->first();

        // A draft cannot jump straight to received (reserved for Part 2).
        $this->actingAs($admin)
            ->patch("/erp/purchasing/orders/{$purchaseOrder->id}/status", ['status' => 'received'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'status' => 'draft']);
    }

    public function test_a_confirmed_order_cannot_be_received_before_part_two(): void
    {
        $admin = $this->admin();

        $purchaseOrder = PurchaseOrder::factory()->confirmed()->create([
            'supplier_id' => $this->supplier()->id,
        ]);

        $this->actingAs($admin)
            ->patch("/erp/purchasing/orders/{$purchaseOrder->id}/status", ['status' => 'received'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'status' => 'confirmed']);
    }

    public function test_a_received_order_can_never_be_cancelled(): void
    {
        $admin = $this->admin();

        $purchaseOrder = PurchaseOrder::factory()->received()->create([
            'supplier_id' => $this->supplier()->id,
        ]);

        $this->actingAs($admin)
            ->patch("/erp/purchasing/orders/{$purchaseOrder->id}/status", ['status' => 'cancelled'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'status' => 'received']);
    }

    public function test_staff_cannot_change_order_status(): void
    {
        $admin = $this->admin();
        $product = $this->product(10);
        $this->actingAs($admin)->post('/erp/purchasing/orders', $this->orderPayload($product, 1));

        $purchaseOrder = PurchaseOrder::latest('id')->first();

        $this->actingAs($this->staff())
            ->patch("/erp/purchasing/orders/{$purchaseOrder->id}/status", ['status' => 'confirmed'])
            ->assertForbidden();
    }

    public function test_the_order_detail_page_renders_items_and_transitions(): void
    {
        $admin = $this->admin();
        $product = $this->product(10, 30);
        $this->actingAs($admin)->post('/erp/purchasing/orders', $this->orderPayload($product, 2));

        $purchaseOrder = PurchaseOrder::latest('id')->first();

        $this->actingAs($this->staff())
            ->get("/erp/purchasing/orders/{$purchaseOrder->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Purchasing/Orders/Show')
                ->where('purchaseOrder.id', $purchaseOrder->id)
                ->where('purchaseOrder.total', 60)
                ->has('purchaseOrder.items', 1)
                ->where('purchaseOrder.supplier.name', $this->supplier()->name)
                ->where('purchaseOrder.available_transitions', ['confirmed', 'cancelled']));
    }

    public function test_an_admin_can_delete_a_draft_purchase_order(): void
    {
        $admin = $this->admin();
        $product = $this->product(10);
        $this->actingAs($admin)->post('/erp/purchasing/orders', $this->orderPayload($product, 1));

        $purchaseOrder = PurchaseOrder::latest('id')->first();

        $this->actingAs($admin)
            ->delete("/erp/purchasing/orders/{$purchaseOrder->id}")
            ->assertRedirect(route('erp.purchasing.orders.index'));

        $this->assertSoftDeleted('purchase_orders', ['id' => $purchaseOrder->id]);
    }

    public function test_a_received_purchase_order_cannot_be_deleted(): void
    {
        $admin = $this->admin();

        $purchaseOrder = PurchaseOrder::factory()->received()->create([
            'supplier_id' => $this->supplier()->id,
        ]);

        $this->actingAs($admin)
            ->delete("/erp/purchasing/orders/{$purchaseOrder->id}")
            ->assertSessionHas('error', 'A received purchase order cannot be deleted.');

        $this->assertNotSoftDeleted('purchase_orders', ['id' => $purchaseOrder->id]);
    }
}
