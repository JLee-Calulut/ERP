<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountingIntegrationTest extends TestCase
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

    private function product(int $stock, float $cost, float $price): Product
    {
        return Product::create([
            'name' => 'Accounting Integration Item',
            'sku' => 'ACC-'.fake()->unique()->numberBetween(1000, 9999),
            'cost_price' => $cost,
            'selling_price' => $price,
            'stock_quantity' => $stock,
            'low_stock_threshold' => 5,
        ]);
    }

    /**
     * Create a confirmed order, bill it, and return the resulting invoice.
     *
     * Order total is price * quantity, so two units at $50 invoice for $100.
     */
    private function invoice(float $price = 50, int $quantity = 2): Invoice
    {
        $admin = $this->admin();
        $product = $this->product(10, 20, $price);

        $this->actingAs($admin)
            ->post('/erp/sales/orders', [
                'customer_id' => Customer::first()->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => $quantity],
                ],
            ]);
        $order = SalesOrder::latest('id')->first();
        $this->actingAs($admin)
            ->patch("/erp/sales/orders/{$order->id}/status", ['status' => 'confirmed']);
        $this->actingAs($admin)
            ->post("/erp/sales/orders/{$order->id}/invoice");

        $invoice = Invoice::where('sales_order_id', $order->id)->firstOrFail();
        Auth::logout();

        return $invoice;
    }

    /**
     * Create a confirmed purchase order, receive it, and return the receipt.
     *
     * Three units at $25 cost receipt for a $75 total against the first
     * seeded supplier (TechSource Distribution).
     */
    private function receipt(): PurchaseReceipt
    {
        $admin = $this->admin();
        $product = $this->product(10, 25, 50);

        $this->actingAs($admin)
            ->post('/erp/purchasing/orders', [
                'supplier_id' => Supplier::first()->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 3],
                ],
            ]);
        $purchaseOrder = PurchaseOrder::latest('id')->first();
        $this->actingAs($admin)
            ->patch("/erp/purchasing/orders/{$purchaseOrder->id}/status", ['status' => 'confirmed']);
        $this->actingAs($admin)
            ->post("/erp/purchasing/orders/{$purchaseOrder->id}/receipts", [
                'items' => [
                    ['purchase_order_item_id' => $purchaseOrder->items->first()->id, 'quantity_received' => 3],
                ],
            ]);

        $receipt = PurchaseReceipt::where('purchase_order_id', $purchaseOrder->id)->firstOrFail();
        Auth::logout();

        return $receipt;
    }

    // ------------------------------------------------------------------
    // Invoices
    // ------------------------------------------------------------------

    public function test_posting_an_invoice_requires_authentication(): void
    {
        $invoice = $this->invoice();

        $this->post("/erp/sales/invoices/{$invoice->id}/post")
            ->assertRedirect(route('erp.login'));
    }

    public function test_staff_without_the_accounting_manage_permission_cannot_post_an_invoice(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($this->staff())
            ->post("/erp/sales/invoices/{$invoice->id}/post")
            ->assertForbidden();

        $this->assertNull($invoice->fresh()->journal_entry_id);
        $this->assertDatabaseCount('journal_entries', 15);
    }

    public function test_posting_an_invoice_creates_a_balanced_entry_referencing_the_invoice(): void
    {
        $invoice = $this->invoice(50, 2); // $100
        $receivable = Account::where('code', '1020')->firstOrFail();
        $revenue = Account::where('code', '4010')->firstOrFail();

        $this->actingAs($this->admin())
            ->post("/erp/sales/invoices/{$invoice->id}/post")
            ->assertRedirect(route('erp.sales.invoices.show', $invoice));

        $invoice->refresh();
        $entry = $invoice->journalEntry;

        $this->assertNotNull($entry);
        $this->assertNotNull($invoice->posted_at);
        $this->assertStringStartsWith('JE-', $entry->entry_number);
        $this->assertStringContainsString($invoice->invoice_number, $entry->description);

        // One balanced DR/CR pair for the invoice total: AR debited, revenue credited.
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $receivable->id,
            'debit' => 100,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $revenue->id,
            'debit' => 0,
            'credit' => 100,
        ]);

        $this->assertEqualsWithDelta(100.0, (float) $entry->total(), 0.01);
        $this->assertEqualsWithDelta(
            (float) $entry->lines()->sum('debit'),
            (float) $entry->lines()->sum('credit'),
            0.01
        );
        $this->assertCount(2, $entry->lines);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'journal_entry_id' => $entry->id,
        ]);
    }

    public function test_an_already_posted_invoice_cannot_be_posted_again(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($this->admin())
            ->post("/erp/sales/invoices/{$invoice->id}/post")
            ->assertRedirect();

        $entryId = $invoice->fresh()->journal_entry_id;
        $this->assertNotNull($entryId);

        // Re-posting mirrors the one-invoice rule: rejected, no second entry.
        $this->actingAs($this->admin())
            ->post("/erp/sales/invoices/{$invoice->id}/post")
            ->assertSessionHasErrors('invoice');

        $this->assertSame($entryId, $invoice->fresh()->journal_entry_id);
        // 12 seeded entries + the single posting above, and nothing more.
        $this->assertDatabaseCount('journal_entries', 16);
    }

    public function test_the_invoice_detail_page_exposes_posting_state(): void
    {
        $invoice = Invoice::first();

        $this->actingAs($this->admin())
            ->get("/erp/sales/invoices/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Sales/Invoices/Show')
                ->where('invoice.posted', false));

        $this->actingAs($this->admin())
            ->post("/erp/sales/invoices/{$invoice->id}/post")
            ->assertRedirect();

        $this->actingAs($this->admin())
            ->get("/erp/sales/invoices/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Sales/Invoices/Show')
                ->where('invoice.posted', true)
                ->where('invoice.journal_entry_number', $invoice->fresh()->journalEntry->entry_number));
    }

    // ------------------------------------------------------------------
    // Purchase receipts
    // ------------------------------------------------------------------

    public function test_posting_a_receipt_requires_authentication(): void
    {
        $receipt = $this->receipt();

        $this->post("/erp/purchasing/receipts/{$receipt->id}/post")
            ->assertRedirect(route('erp.login'));
    }

    public function test_staff_without_the_accounting_manage_permission_cannot_post_a_receipt(): void
    {
        $receipt = $this->receipt();

        $this->actingAs($this->staff())
            ->post("/erp/purchasing/receipts/{$receipt->id}/post")
            ->assertForbidden();

        $this->assertNull($receipt->fresh()->journal_entry_id);
        $this->assertDatabaseCount('journal_entries', 15);
    }

    public function test_posting_a_receipt_creates_a_balanced_entry_referencing_the_supplier(): void
    {
        $receipt = $this->receipt(); // $75 from TechSource Distribution
        $inventory = Account::where('code', '1030')->firstOrFail();
        $payable = Account::where('code', '2010')->firstOrFail();

        $this->actingAs($this->admin())
            ->post("/erp/purchasing/receipts/{$receipt->id}/post")
            ->assertRedirect(route('erp.purchasing.receipts.show', $receipt));

        $receipt->refresh();
        $entry = $receipt->journalEntry;

        $this->assertNotNull($entry);
        $this->assertNotNull($receipt->posted_at);
        $this->assertStringStartsWith('JE-', $entry->entry_number);
        $this->assertStringContainsString((string) $receipt->id, $entry->description);
        $this->assertStringContainsString('TechSource Distribution', $entry->description);

        // One balanced DR/CR pair for the receipt total: inventory debited, payable credited.
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $inventory->id,
            'debit' => 75,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_entry_lines', [
            'journal_entry_id' => $entry->id,
            'account_id' => $payable->id,
            'debit' => 0,
            'credit' => 75,
        ]);

        $this->assertEqualsWithDelta(75.0, (float) $entry->total(), 0.01);
        $this->assertEqualsWithDelta(
            (float) $entry->lines()->sum('debit'),
            (float) $entry->lines()->sum('credit'),
            0.01
        );
        $this->assertCount(2, $entry->lines);
        $this->assertDatabaseHas('purchase_receipts', [
            'id' => $receipt->id,
            'journal_entry_id' => $entry->id,
        ]);
    }

    public function test_an_already_posted_receipt_cannot_be_posted_again(): void
    {
        $receipt = $this->receipt();

        $this->actingAs($this->admin())
            ->post("/erp/purchasing/receipts/{$receipt->id}/post")
            ->assertRedirect();

        $entryId = $receipt->fresh()->journal_entry_id;
        $this->assertNotNull($entryId);

        $this->actingAs($this->admin())
            ->post("/erp/purchasing/receipts/{$receipt->id}/post")
            ->assertSessionHasErrors('receipt');

        $this->assertSame($entryId, $receipt->fresh()->journal_entry_id);
        $this->assertDatabaseCount('journal_entries', 16);
    }

    public function test_the_receipt_detail_page_exposes_posting_state(): void
    {
        // The seeded TechSource receipt is already posted by the seeder, so
        // build a fresh unposted receipt to exercise the one-time flow.
        $receipt = $this->receipt();

        $this->actingAs($this->admin())
            ->get("/erp/purchasing/receipts/{$receipt->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Purchasing/Receipts/Show')
                ->where('receipt.posted', false));

        $this->actingAs($this->admin())
            ->post("/erp/purchasing/receipts/{$receipt->id}/post")
            ->assertRedirect();

        $this->actingAs($this->admin())
            ->get("/erp/purchasing/receipts/{$receipt->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Erp/Purchasing/Receipts/Show')
                ->where('receipt.posted', true)
                ->where('receipt.journal_entry_number', $receipt->fresh()->journalEntry->entry_number));
    }
}
