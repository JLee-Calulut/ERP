<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesService
{
    private InventoryService $inventory;

    public function __construct(InventoryService $inventory)
    {
        $this->inventory = $inventory;
    }

    /**
     * Create a sales order with line items atomically and decrement stock.
     *
     * Line pricing comes from the product's current selling price. Each
     * product is locked for update so concurrent orders cannot oversell.
     *
     * @throws ValidationException when any line exceeds available stock.
     */
    public function createOrder(array $data, ?User $user = null): SalesOrder
    {
        return DB::transaction(function () use ($data, $user) {
            /** @var Collection<int, Product> $products */
            $products = Product::query()
                ->whereIn('id', collect($data['items'])->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = [];
            $subtotal = 0.0;

            foreach ($data['items'] as $index => $item) {
                $product = $products->get($item['product_id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => 'The selected product is invalid.',
                    ]);
                }

                $quantity = (int) $item['quantity'];

                if ((int) $product->stock_quantity < $quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => "Insufficient stock for \"{$product->name}\" ({$product->stock_quantity} available).",
                    ]);
                }

                $unitPrice = (float) $product->selling_price;
                $lines[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => round($unitPrice * $quantity, 2),
                ];
                $subtotal += $unitPrice * $quantity;
            }

            /** @var SalesOrder $order */
            $order = SalesOrder::create([
                'customer_id' => $data['customer_id'],
                'status' => SalesOrder::STATUS_DRAFT,
                'subtotal' => round($subtotal, 2),
                'total' => round($subtotal, 2),
                'ordered_at' => now(),
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                ]);

                $this->inventory->adjust(
                    $line['product'],
                    'out',
                    $line['quantity'],
                    "Sale order #{$order->id}",
                    $user,
                );
            }

            return $order;
        });
    }

    /**
     * Move an order through its allowed status flow.
     *
     * @throws ValidationException on an illegal transition.
     */
    public function transitionStatus(SalesOrder $order, string $status): void
    {
        if (! $order->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => 'The order cannot move to that status from its current one.',
            ]);
        }

        $order->update(['status' => $status]);
    }

    /**
     * Generate the single invoice for a confirmed or completed order.
     *
     * The invoice number is derived from the row id, so it stays unique
     * even under concurrent creation.
     *
     * @throws ValidationException when the order is not eligible or already invoiced.
     */
    public function generateInvoice(SalesOrder $order): Invoice
    {
        return DB::transaction(function () use ($order) {
            $order->refresh();

            if ($order->invoice()->exists()) {
                throw ValidationException::withMessages([
                    'invoice' => 'This order already has an invoice.',
                ]);
            }

            if (! in_array($order->status, [SalesOrder::STATUS_CONFIRMED, SalesOrder::STATUS_COMPLETED], true)) {
                throw ValidationException::withMessages([
                    'invoice' => 'Only confirmed or completed orders can be invoiced.',
                ]);
            }

            $invoice = $order->invoice()->create([
                'invoice_number' => null,
                'issued_at' => now(),
                'total' => $order->total,
                'status' => Invoice::STATUS_UNPAID,
            ]);

            $invoice->forceFill([
                'invoice_number' => 'INV-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT),
            ])->save();

            return $invoice;
        });
    }
}
