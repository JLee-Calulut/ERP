<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchasingService
{
    private InventoryService $inventory;

    public function __construct(InventoryService $inventory)
    {
        $this->inventory = $inventory;
    }

    /**
     * Create a purchase order with line items atomically.
     *
     * Lines are priced from the product's current cost price (catalog
     * pricing) and the subtotal/total are computed for the order. This
     * step performs no stock movements — those are booked by receive().
     *
     * @throws ValidationException when a line references an unknown product.
     */
    public function createPurchaseOrder(array $data, ?User $user = null): PurchaseOrder
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
                $unitCost = (float) $product->cost_price;
                $lines[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'line_total' => round($unitCost * $quantity, 2),
                ];
                $subtotal += $unitCost * $quantity;
            }

            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $data['supplier_id'],
                'status' => PurchaseOrder::STATUS_DRAFT,
                'ordered_at' => now(),
                'subtotal' => round($subtotal, 2),
                'total' => round($subtotal, 2),
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            foreach ($lines as $line) {
                $purchaseOrder->items()->create([
                    'product_id' => $line['product']->id,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'line_total' => $line['line_total'],
                ]);
            }

            return $purchaseOrder;
        });
    }

    /**
     * Receive stock against a confirmed purchase order and record a receipt.
     *
     * Each received line posts an "in" stock movement through InventoryService
     * (mirroring the "out" moves SalesService books when orders are confirmed),
     * which raises stock atomically and appends to the stock-transaction
     * ledger. The total received per line can never exceed the ordered
     * quantity. When every line is fully received the order is transitioned to
     * "received"; a partial receipt keeps it "confirmed" so later receipts can
     * accumulate.
     *
     * @param  array<int, array{purchase_order_item_id: int, quantity_received: int}>  $receiptItems
     *
     * @throws ValidationException when the order cannot be received or a line
     *                             would be over-received.
     */
    public function receive(PurchaseOrder $purchaseOrder, array $receiptItems, ?string $notes = null, ?User $user = null): PurchaseReceipt
    {
        return DB::transaction(function () use ($purchaseOrder, $receiptItems, $notes, $user) {
            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = PurchaseOrder::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if ($purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'order' => 'A cancelled purchase order cannot be received.',
                ]);
            }

            if ($purchaseOrder->status !== PurchaseOrder::STATUS_CONFIRMED) {
                throw ValidationException::withMessages([
                    'order' => 'Only confirmed purchase orders can be received.',
                ]);
            }

            /** @var Collection<int, PurchaseOrderItem> $itemsById */
            $itemsById = $purchaseOrder->items->keyBy('id');

            // Quantities already received per order line across earlier receipts.
            $receivedByItem = PurchaseReceiptItem::query()
                ->whereIn('purchase_order_item_id', $itemsById->keys())
                ->selectRaw('purchase_order_item_id, SUM(quantity_received) AS total_received')
                ->groupBy('purchase_order_item_id')
                ->pluck('total_received', 'purchase_order_item_id')
                ->map(fn ($value) => (int) $value)
                ->toArray();

            // Lock the products so concurrent receipts cannot over-adjust stock.
            /** @var Collection<int, Product> $products */
            $products = Product::query()
                ->whereIn('id', $itemsById->pluck('product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = [];
            $total = 0.0;

            foreach ($receiptItems as $index => $entry) {
                /** @var PurchaseOrderItem|null $line */
                $line = $itemsById->get($entry['purchase_order_item_id']);

                if (! $line) {
                    throw ValidationException::withMessages([
                        "items.{$index}.purchase_order_item_id" => 'The selected line item does not belong to this order.',
                    ]);
                }

                $quantity = (int) $entry['quantity_received'];
                $alreadyReceived = $receivedByItem[$line->id] ?? 0;
                $ordered = (int) $line->quantity;

                if ($alreadyReceived + $quantity > $ordered) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity_received" => sprintf(
                            'Cannot receive more than the ordered quantity of "%s".',
                            $line->product?->name ?? 'this line item'
                        ),
                    ]);
                }

                $product = $products->get($line->product_id);

                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity_received" => 'The product for this line is no longer available.',
                    ]);
                }

                // Remember the accumulation even if a later line throws, so a
                // failed receipt never partially books stock.
                $receivedByItem[$line->id] = $alreadyReceived + $quantity;

                $lines[] = [
                    'line' => $line,
                    'product' => $product,
                    'quantity' => $quantity,
                ];

                $total += (float) $line->unit_cost * $quantity;
            }

            /** @var PurchaseReceipt $receipt */
            $receipt = PurchaseReceipt::create([
                'purchase_order_id' => $purchaseOrder->id,
                'received_at' => now(),
                'total' => round($total, 2),
                'notes' => $notes,
                'received_by' => $user?->id,
            ]);

            foreach ($lines as $receiptLine) {
                /** @var PurchaseOrderItem $line */
                $line = $receiptLine['line'];

                $receipt->items()->create([
                    'purchase_order_item_id' => $line->id,
                    'quantity_received' => $receiptLine['quantity'],
                ]);

                $this->inventory->adjust(
                    $receiptLine['product'],
                    'in',
                    $receiptLine['quantity'],
                    "Purchase receipt #{$receipt->id}",
                    $user,
                );
            }

            // Close the order once every line has been fully received.
            $fullyReceived = $purchaseOrder->items->every(
                fn (PurchaseOrderItem $line) => ($receivedByItem[$line->id] ?? 0) >= (int) $line->quantity
            );

            if ($fullyReceived) {
                $purchaseOrder->update(['status' => PurchaseOrder::STATUS_RECEIVED]);
            }

            return $receipt;
        });
    }

    /**
     * Move a purchase order through its allowed status flow.
     *
     * Part 1 allows draft -> confirmed, draft -> cancelled and
     * confirmed -> cancelled. Receiving stock (confirmed -> received) is
     * booked through receive() so inventory is always updated atomically; a
     * received order can never be cancelled.
     *
     * @throws ValidationException on an illegal transition.
     */
    public function transitionStatus(PurchaseOrder $purchaseOrder, string $status): void
    {
        if ($status === PurchaseOrder::STATUS_RECEIVED) {
            throw ValidationException::withMessages([
                'status' => 'Receive stock against this order instead of changing its status directly.',
            ]);
        }

        if (! $purchaseOrder->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => 'The purchase order cannot move to that status from its current one.',
            ]);
        }

        $purchaseOrder->update(['status' => $status]);
    }
}
