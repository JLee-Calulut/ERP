<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PurchaseOrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Services\PurchasingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $search = trim((string) $request->query('search'));
        $status = $request->query('status');

        $purchaseOrders = PurchaseOrder::query()
            ->with('supplier')
            ->withCount('items')
            ->when($search !== '', fn ($query) => $query
                ->whereHas('supplier', fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('ordered_at')
            ->get()
            ->map(fn (PurchaseOrder $purchaseOrder) => [
                'id' => $purchaseOrder->id,
                'supplier' => $purchaseOrder->supplier?->name,
                'status' => $purchaseOrder->status,
                'status_label' => $purchaseOrder->statusLabel(),
                'total' => $purchaseOrder->total,
                'items_count' => $purchaseOrder->items_count,
                'ordered_at' => $purchaseOrder->ordered_at?->toDateString(),
                'ordered_at_readable' => $purchaseOrder->ordered_at?->diffForHumans(),
            ]);

        return Inertia::render('Erp/Purchasing/Orders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'statuses' => PurchaseOrder::STATUSES,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', PurchaseOrder::class);

        return Inertia::render('Erp/Purchasing/Orders/Form', [
            'suppliers' => $this->supplierOptions(),
            'products' => $this->productOptions(),
        ]);
    }

    public function store(PurchaseOrderRequest $request, PurchasingService $service): RedirectResponse
    {
        Gate::authorize('create', PurchaseOrder::class);

        $purchaseOrder = $service->createPurchaseOrder($request->validated(), auth()->user());

        return redirect()
            ->route('erp.purchasing.orders.show', $purchaseOrder)
            ->with('success', "Purchase order #{$purchaseOrder->id} created.");
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        Gate::authorize('view', $purchaseOrder);

        $receivedByItem = PurchaseReceiptItem::query()
            ->whereIn('purchase_order_item_id', $purchaseOrder->items->pluck('id'))
            ->selectRaw('purchase_order_item_id, SUM(quantity_received) AS total_received')
            ->groupBy('purchase_order_item_id')
            ->pluck('total_received', 'purchase_order_item_id')
            ->map(fn ($value) => (int) $value)
            ->toArray();

        return Inertia::render('Erp/Purchasing/Orders/Show', [
            'purchaseOrder' => [
                'id' => $purchaseOrder->id,
                'status' => $purchaseOrder->status,
                'status_label' => $purchaseOrder->statusLabel(),
                'subtotal' => $purchaseOrder->subtotal,
                'total' => $purchaseOrder->total,
                'notes' => $purchaseOrder->notes,
                'ordered_at' => $purchaseOrder->ordered_at?->toDateTimeString(),
                'ordered_at_readable' => $purchaseOrder->ordered_at?->diffForHumans(),
                'created_by' => $purchaseOrder->creator?->name,
                'supplier' => $purchaseOrder->supplier ? [
                    'id' => $purchaseOrder->supplier->id,
                    'name' => $purchaseOrder->supplier->name,
                    'email' => $purchaseOrder->supplier->email,
                    'phone' => $purchaseOrder->supplier->phone,
                    'city' => $purchaseOrder->supplier->address_city,
                    'country' => $purchaseOrder->supplier->address_country,
                ] : null,
                'items' => $purchaseOrder->items
                    ->map(fn ($item) => [
                        'id' => $item->id,
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'sku' => $item->product->sku,
                        ] : null,
                        'quantity' => $item->quantity,
                        'unit_cost' => $item->unit_cost,
                        'line_total' => $item->line_total,
                        'received' => $receivedByItem[$item->id] ?? 0,
                        'remaining' => max(0, (int) $item->quantity - ($receivedByItem[$item->id] ?? 0)),
                    ])
                    ->values(),
                'available_transitions' => array_values(array_diff(
                    PurchaseOrder::TRANSITIONS[$purchaseOrder->status] ?? [],
                    [PurchaseOrder::STATUS_RECEIVED]
                )),
                // A confirmed order can be received against; the action is
                // offered separately from the status transition buttons.
                'can_receive' => $purchaseOrder->status === PurchaseOrder::STATUS_CONFIRMED,
                'receipts' => $purchaseOrder->receipts
                    ->sortByDesc('received_at')
                    ->map(fn (PurchaseReceipt $receipt) => [
                        'id' => $receipt->id,
                        'total' => $receipt->total,
                        'received_by' => $receipt->receiver?->name,
                        'received_at' => $receipt->received_at?->toDateTimeString(),
                        'received_at_readable' => $receipt->received_at?->diffForHumans(),
                    ])
                    ->values(),
            ],
        ]);
    }

    public function updateStatus(Request $request, PurchaseOrder $purchaseOrder, PurchasingService $service): RedirectResponse
    {
        Gate::authorize('updateStatus', $purchaseOrder);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', PurchaseOrder::STATUSES)],
        ]);

        $service->transitionStatus($purchaseOrder, $data['status']);

        return redirect()
            ->route('erp.purchasing.orders.show', $purchaseOrder)
            ->with('success', "Purchase order #{$purchaseOrder->id} is now {$purchaseOrder->statusLabel()}.");
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        Gate::authorize('delete', $purchaseOrder);

        if ($purchaseOrder->status === PurchaseOrder::STATUS_RECEIVED) {
            return redirect()
                ->route('erp.purchasing.orders.show', $purchaseOrder)
                ->with('error', 'A received purchase order cannot be deleted.');
        }

        $purchaseOrder->delete();

        return redirect()
            ->route('erp.purchasing.orders.index')
            ->with('success', "Purchase order #{$purchaseOrder->id} deleted.");
    }

    private function supplierOptions(): array
    {
        return Supplier::orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'email' => $supplier->email,
            ])
            ->values()
            ->all();
    }

    private function productOptions(): array
    {
        return Product::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'cost_price' => $product->cost_price,
                'stock_quantity' => $product->stock_quantity,
            ])
            ->values()
            ->all();
    }
}
