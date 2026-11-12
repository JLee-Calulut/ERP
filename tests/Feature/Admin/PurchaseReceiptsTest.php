<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseReceiptsTest extends TestCase
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
            'name' => 'Receipt Test Item',
            'sku' => 'RCT-'.fake()->unique()->numberBetween(1000, 9999),
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

    /**
     * Create and confirm a purchase order with one line.
     *
     * @return array{0: PurchaseOrder, 1: PurchaseOrderItem, 2: User}
     */
    private function confirmedOrder(Product $product, int $quantity): array
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/erp/purchasing/orders', [
                'supplier_id' => $this->supplier()->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => $quantity],
                ],
            ]);

        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = PurchaseOrder::latest('id')->first();

        $this->actingAs($admin)
            ->patch("/erp/purchasing/orders/{$purchaseOrder->id}/status", ['status' => 'confirmed']);

        return [$purchaseOrder->refresh(), $purchaseOrder->items->first(), $admin];
    }

    private function receiptPayload(PurchaseOrderItem $item, int $quantity, array $extra = []): array
    {
        return array_merge([
            'items' => [
                ['purchase_order_item_id' => $item->id, 'quantity_received' => $quantity],
            ],
        ], $extra);
    }

    public function test_purchase_receipts_require_authentication(): void
    {
        $this->get('/erp/purchasing/receipts')->assertRedirect(route('erp.login'));
    }

    public function test_purchase_receipts_require_the_purchasing_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/erp/purchasing/receipts')
            ->assertForbidden();
    }

    public function test_staff_can_view_the_receipts_index(): void
    {
        $this->actingAs($this->staff())
            ->get('/erp/purchasing/receipts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Purchasing/Receipts/Index')
                ->has('receipts', 1)
                ->where('receipts.0.supplier', 'TechSource Distribution'));
    }

    public function test_the_receipt_detail_page_renders(): void
    {
        $admin = $this->admin();
        $receipt = PurchaseReceipt::first();

        $this->actingAs($admin)
            ->get("/erp/purchasing/receipts/{$receipt->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Purchasing/Receipts/Show')
                ->where('receipt.id', $receipt->id)
                ->where('receipt.total', (int) $receipt->total)
                ->where('receipt.purchase_order.id', $receipt->purchase_order_id)
                ->where('receipt.supplier.name', 'TechSource Distribution')
                ->has('receipt.items', 3));
    }

    public function test_creating_a_receipt_requires_the_purchasing_manage_permission(): void
    {
        $product = $this->product(10);
        [$purchaseOrder, $item] = $this->confirmedOrder($product, 3);

        $this->actingAs($this->staff())
            ->post("/erp/purchasing/orders/{$purchaseOrder->id}/receipts", $this->receiptPayload($item, 3))
            ->assertForbidden();
    }

    public function test_receiving_a_confirmed_order_increases_stock_and_writes_in_transactions(): void
    {
        $product = $this->product(10, 25);
        [$purchaseOrder, $item, $admin] = $this->confirmedOrder($product, 3);

        $this->actingAs($admin)
            ->post("/erp/purchasing/orders/{$purchaseOrder->id}/receipts", $this->receiptPayload($item, 3))
            ->assertRedirect(route('erp.purchasing.receipts.show', PurchaseReceipt::latest('id')->first()));

        // Stock is raised atomically.
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 13]);

        // An "in" transaction is written with the receipt as its reason.
        $this->assertDatabaseHas('stock_transactions', [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 3,
            'previous_quantity' => 10,
            'new_quantity' => 13,
            'user_id' => $admin->id,
        ]);

        $receipt = PurchaseReceipt::firstWhere('purchase_order_id', $purchaseOrder->id);
        $this->assertNotNull($receipt);
        $this->assertSame(75.0, (float) $receipt->total);
        $this->assertSame($admin->id, $receipt->received_by);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $item->id,
            'quantity_received' => 3,
        ]);

        // A fully received order closes as "received".
        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'status' => PurchaseOrder::STATUS_RECEIVED]);
    }

    public function test_over_receiving_past_ordered_quantity_is_rejected(): void
    {
        $product = $this->product(10, 25);
        [$purchaseOrder, $item, $admin] = $this->confirmedOrder($product, 2);

        $this->actingAs($admin)
            ->post("/erp/purchasing/orders/{$purchaseOrder->id}/receipts", $this->receiptPayload($item, 3))
            ->assertSessionHasErrors('items.0.quantity_received');

        // Nothing is persisted and stock is untouched.
        $this->assertDatabaseCount('purchase_receipts', 1);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 10]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'status' => PurchaseOrder::STATUS_CONFIRMED]);
    }

    public function test_a_fully_received_order_cannot_be_received_again(): void
    {
        $product = $this->product(20, 25);
        [$purchaseOrder, $item, $admin] = $this->confirmedOrder($product, 5);

        $this->actingAs($admin)
            ->post("/erp/purchasing/orders/{$purchaseOrder->id}/receipts", $this->receiptPayload($item, 5))
            ->assertRedirect();

        // Receiving again is rejected: the order is no longer confirmed.
        $this->actingAs($admin)
            ->post("/erp/purchasing/orders/{$purchaseOrder->id}/receipts", $this->receiptPayload($item, 1))
            ->assertSessionHasErrors('order');

        $this->assertSame(1, PurchaseReceipt::where('purchase_order_id', $purchaseOrder->id)->count());
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 25]);
    }

    public function test_partial_receipts_stay_confirmed_and_accumulate(): void
    {
        $product = $this->product(10, 20);
        [$purchaseOrder, $item, $admin] = $this->confirmedOrder($product, 4);

        // First partial receipt: 3 of 4 units.
        $this->actingAs($admin)
            ->post("/erp/purchasing/orders/{$purchaseOrder->id}/receipts", $this->receiptPayload($item, 3))
            ->assertRedirect();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 13]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'status' => PurchaseOrder::STATUS_CONFIRMED]);
        $this->assertSame(60.0, (float) PurchaseReceipt::where('purchase_order_id', $purchaseOrder->id)->first()->total);

        // The remaining unit completes the order.
        $this->actingAs($admin)
            ->post("/erp/purchasing/orders/{$purchaseOrder->id}/receipts", $this->receiptPayload($item, 1))
            ->assertRedirect();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 14]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'status' => PurchaseOrder::STATUS_RECEIVED]);
        $this->assertSame(2, PurchaseReceipt::where('purchase_order_id', $purchaseOrder->id)->count());
        $this->assertSame(4, (int) PurchaseReceiptItem::where('purchase_order_item_id', $item->id)->sum('quantity_received'));
    }

    public function test_a_draft_order_cannot_be_received(): void
    {
        $admin = $this->admin();
        $product = $this->product(10);

        $this->actingAs($admin)
            ->post('/erp/purchasing/orders', [
                'supplier_id' => $this->supplier()->id,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
            ]);

        $purchaseOrder = PurchaseOrder::latest('id')->first();

        $this->actingAs($admin)
            ->post("/erp/purchasing/orders/{$purchaseOrder->id}/receipts", [
                'items' => [
                    ['purchase_order_item_id' => $purchaseOrder->items->first()->id, 'quantity_received' => 2],
                ],
            ])
            ->assertSessionHasErrors('order');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 10]);
    }

    public function test_a_cancelled_order_cannot_be_received(): void
    {
        $product = $this->product(10);
        [$purchaseOrder, $item, $admin] = $this->confirmedOrder($product, 3);

        $this->actingAs($admin)
            ->patch("/erp/purchasing/orders/{$purchaseOrder->id}/status", ['status' => 'cancelled']);

        $this->actingAs($admin)
            ->post("/erp/purchasing/orders/{$purchaseOrder->id}/receipts", $this->receiptPayload($item, 3))
            ->assertSessionHasErrors('order');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 10]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'status' => PurchaseOrder::STATUS_CANCELLED]);
    }

    public function test_the_confirmed_order_detail_page_offers_receiving(): void
    {
        $product = $this->product(10, 30);
        [$purchaseOrder, $item] = $this->confirmedOrder($product, 6);

        $this->actingAs($this->staff())
            ->get("/erp/purchasing/orders/{$purchaseOrder->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Purchasing/Orders/Show')
                ->where('purchaseOrder.can_receive', true)
                ->where('purchaseOrder.items.0.remaining', 6)
                ->where('purchaseOrder.items.0.received', 0)
                ->has('purchaseOrder.receipts'));
    }
}
