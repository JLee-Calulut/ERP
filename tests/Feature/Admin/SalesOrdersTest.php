<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesOrdersTest extends TestCase
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

    private function product(int $stock, float $price = 25): Product
    {
        return Product::create([
            'name' => 'Sales Test Item',
            'sku' => 'SALE-'.fake()->unique()->numberBetween(1000, 9999),
            'cost_price' => 10,
            'selling_price' => $price,
            'stock_quantity' => $stock,
            'low_stock_threshold' => 5,
        ]);
    }

    private function customer(): Customer
    {
        return Customer::first();
    }

    private function orderPayload(Product $product, int $quantity): array
    {
        return [
            'customer_id' => $this->customer()->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => $quantity],
            ],
        ];
    }

    public function test_sales_orders_require_authentication(): void
    {
        $this->get('/erp/sales/orders')->assertRedirect(route('erp.login'));
    }

    public function test_sales_orders_require_the_sales_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/erp/sales/orders')
            ->assertForbidden();
    }

    public function test_staff_can_view_the_orders_index(): void
    {
        $this->actingAs($this->staff())
            ->get('/erp/sales/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Sales/Orders/Index')
                ->has('orders', 7)
                ->has('statuses', 4)
                ->has('filters'));
    }

    public function test_creating_an_order_requires_the_sales_manage_permission(): void
    {
        $product = $this->product(10);

        $this->actingAs($this->staff())
            ->post('/erp/sales/orders', $this->orderPayload($product, 1))
            ->assertForbidden();
    }

    public function test_admin_can_view_the_create_order_form(): void
    {
        $this->actingAs($this->admin())
            ->get('/erp/sales/orders/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Sales/Orders/Form')
                ->has('customers', 4)
                ->has('products', 12));
    }

    public function test_admin_can_create_an_order_that_decrements_stock(): void
    {
        $admin = $this->admin();
        $product = $this->product(10, 25);

        $this->actingAs($admin)
            ->post('/erp/sales/orders', $this->orderPayload($product, 3))
            ->assertRedirect();

        $order = SalesOrder::latest('id')->first();

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'customer_id' => $this->customer()->id,
            'status' => SalesOrder::STATUS_DRAFT,
            'subtotal' => 75,
            'total' => 75,
        ]);

        $this->assertDatabaseHas('sales_order_items', [
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 25,
            'line_total' => 75,
        ]);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 7]);
        $this->assertDatabaseHas('stock_transactions', [
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => -3,
            'new_quantity' => 7,
            'user_id' => $admin->id,
        ]);
    }

    public function test_an_order_cannot_exceed_available_stock(): void
    {
        $admin = $this->admin();
        $product = $this->product(2);

        $this->actingAs($admin)
            ->post('/erp/sales/orders', $this->orderPayload($product, 5))
            ->assertSessionHasErrors('items.0.quantity');

        // Nothing is persisted and stock is untouched.
        $this->assertDatabaseCount('sales_orders', 7);
        $this->assertDatabaseCount('sales_order_items', 23);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 2]);
    }

    public function test_stats_of_a_new_order_are_computed_from_catalog_prices(): void
    {
        $admin = $this->admin();
        $mouse = $this->product(10, 45);
        $keyboard = $this->product(10, 90);

        $this->actingAs($admin)
            ->post('/erp/sales/orders', [
                'customer_id' => $this->customer()->id,
                'items' => [
                    ['product_id' => $mouse->id, 'quantity' => 2],
                    ['product_id' => $keyboard->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $order = SalesOrder::latest('id')->first();

        $this->assertEquals(180.0, (float) $order->subtotal);
        $this->assertEquals(180.0, (float) $order->total);
        $this->assertCount(2, $order->items);
        $this->assertDatabaseHas('products', ['id' => $mouse->id, 'stock_quantity' => 8]);
    }

    public function test_an_order_requires_a_customer_and_at_least_one_item(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/erp/sales/orders', ['items' => [['product_id' => 1, 'quantity' => 1]]])
            ->assertSessionHasErrors('customer_id');

        $this->actingAs($admin)
            ->post('/erp/sales/orders', ['customer_id' => $this->customer()->id, 'items' => []])
            ->assertSessionHasErrors('items');
    }

    public function test_an_order_moves_through_the_status_flow(): void
    {
        $admin = $this->admin();
        $product = $this->product(10);
        $this->actingAs($admin)->post('/erp/sales/orders', $this->orderPayload($product, 1));

        $order = SalesOrder::latest('id')->first();

        $this->actingAs($admin)
            ->patch("/erp/sales/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertRedirect(route('erp.sales.orders.show', $order));
        $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'status' => 'confirmed']);

        $this->actingAs($admin)
            ->patch("/erp/sales/orders/{$order->id}/status", ['status' => 'completed'])
            ->assertRedirect(route('erp.sales.orders.show', $order));
        $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'status' => 'completed']);
    }

    public function test_an_illegal_status_transition_is_rejected(): void
    {
        $admin = $this->admin();
        $product = $this->product(10);
        $this->actingAs($admin)->post('/erp/sales/orders', $this->orderPayload($product, 1));

        $order = SalesOrder::latest('id')->first();

        // A draft cannot jump straight to completed.
        $this->actingAs($admin)
            ->patch("/erp/sales/orders/{$order->id}/status", ['status' => 'completed'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'status' => 'draft']);
    }

    public function test_staff_cannot_change_order_status(): void
    {
        $admin = $this->admin();
        $product = $this->product(10);
        $this->actingAs($admin)->post('/erp/sales/orders', $this->orderPayload($product, 1));

        $order = SalesOrder::latest('id')->first();

        $this->actingAs($this->staff())
            ->patch("/erp/sales/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertForbidden();
    }

    public function test_the_order_detail_page_renders_items_and_transitions(): void
    {
        $admin = $this->admin();
        $product = $this->product(10, 30);
        $this->actingAs($admin)->post('/erp/sales/orders', $this->orderPayload($product, 2));

        $order = SalesOrder::latest('id')->first();

        $this->actingAs($this->staff())
            ->get("/erp/sales/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Sales/Orders/Show')
                ->where('order.id', $order->id)
                ->where('order.total', 60)
                ->has('order.items', 1)
                ->where('order.customer.name', $this->customer()->name)
                ->where('order.available_transitions', ['confirmed', 'cancelled']));
    }
}
