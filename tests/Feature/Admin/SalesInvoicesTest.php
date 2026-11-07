<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesInvoicesTest extends TestCase
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

    private function product(int $stock, float $price = 50): Product
    {
        return Product::create([
            'name' => 'Invoice Test Item',
            'sku' => 'INV-'.fake()->unique()->numberBetween(1000, 9999),
            'cost_price' => 20,
            'selling_price' => $price,
            'stock_quantity' => $stock,
            'low_stock_threshold' => 5,
        ]);
    }

    private function completedOrder(): SalesOrder
    {
        $admin = $this->admin();
        $product = $this->product(10, 50);

        $this->actingAs($admin)
            ->post('/erp/sales/orders', [
                'customer_id' => Customer::first()->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);
        $order = SalesOrder::latest('id')->first();
        $this->actingAs($admin)
            ->patch("/erp/sales/orders/{$order->id}/status", ['status' => 'confirmed']);

        return $order;
    }

    public function test_invoices_require_authentication(): void
    {
        $this->get('/erp/sales/invoices')->assertRedirect(route('erp.login'));
    }

    public function test_invoices_require_the_sales_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/erp/sales/invoices')
            ->assertForbidden();
    }

    public function test_staff_can_view_the_invoices_index(): void
    {
        $this->actingAs($this->staff())
            ->get('/erp/sales/invoices')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Sales/Invoices/Index')
                ->has('invoices', 6));
    }

    public function test_generating_an_invoice_requires_the_sales_manage_permission(): void
    {
        $order = $this->completedOrder();

        $this->actingAs($this->staff())
            ->post("/erp/sales/orders/{$order->id}/invoice")
            ->assertForbidden();
    }

    public function test_admin_can_generate_an_invoice_from_an_order(): void
    {
        $order = $this->completedOrder();

        $this->actingAs($this->admin())
            ->post("/erp/sales/orders/{$order->id}/invoice")
            ->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'sales_order_id' => $order->id,
            'total' => 100,
            'status' => Invoice::STATUS_UNPAID,
        ]);

        $invoice = Invoice::where('sales_order_id', $order->id)->first();
        $this->assertNotNull($invoice);
        $this->assertNotNull($invoice->invoice_number);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);
    }

    public function test_invoice_numbers_are_unique(): void
    {
        $this->actingAs($this->admin())
            ->post('/erp/sales/orders/'.$this->completedOrder()->id.'/invoice');

        $numbers = Invoice::pluck('invoice_number')->filter()->all();
        $this->assertCount(7, $numbers);
        $this->assertCount(7, array_unique($numbers));
    }

    public function test_an_order_can_only_have_one_invoice(): void
    {
        $order = $this->completedOrder();

        $this->actingAs($this->admin())
            ->post("/erp/sales/orders/{$order->id}/invoice")
            ->assertRedirect();

        $this->actingAs($this->admin())
            ->post("/erp/sales/orders/{$order->id}/invoice")
            ->assertSessionHasErrors('invoice');

        $this->assertSame(1, Invoice::where('sales_order_id', $order->id)->count());
    }

    public function test_a_draft_order_cannot_be_invoiced(): void
    {
        $product = $this->product(10);
        $this->actingAs($this->admin())
            ->post('/erp/sales/orders', [
                'customer_id' => Customer::first()->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);
        $order = SalesOrder::latest('id')->first();

        $this->actingAs($this->admin())
            ->post("/erp/sales/orders/{$order->id}/invoice")
            ->assertSessionHasErrors('invoice');

        $this->assertSame(0, Invoice::where('sales_order_id', $order->id)->count());
    }

    public function test_the_invoice_detail_page_renders(): void
    {
        $invoice = Invoice::first();

        $this->actingAs($this->admin())
            ->get("/erp/sales/invoices/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Sales/Invoices/Show')
                ->where('invoice.invoice_number', $invoice->invoice_number)
                ->where('invoice.total', (int) $invoice->total)
                ->has('invoice.items'));
    }
}
