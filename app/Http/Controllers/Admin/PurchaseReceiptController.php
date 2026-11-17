<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PurchaseReceiptRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Services\AccountingService;
use App\Services\PurchasingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseReceiptController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', PurchaseReceipt::class);

        $receipts = PurchaseReceipt::with('purchaseOrder.supplier', 'receiver')
            ->latest('received_at')
            ->get()
            ->map(fn (PurchaseReceipt $receipt) => [
                'id' => $receipt->id,
                'purchase_order_id' => $receipt->purchase_order_id,
                'supplier' => $receipt->purchaseOrder?->supplier?->name,
                'total' => $receipt->total,
                'notes' => $receipt->notes,
                'received_by' => $receipt->receiver?->name,
                'received_at' => $receipt->received_at?->toDateString(),
                'received_at_readable' => $receipt->received_at?->diffForHumans(),
            ]);

        return Inertia::render('Erp/Purchasing/Receipts/Index', [
            'receipts' => $receipts,
        ]);
    }

    public function show(PurchaseReceipt $purchaseReceipt): Response
    {
        Gate::authorize('view', $purchaseReceipt);

        $purchaseReceipt->load('purchaseOrder.supplier', 'receiver', 'items.purchaseOrderItem.product', 'journalEntry');

        return Inertia::render('Erp/Purchasing/Receipts/Show', [
            'receipt' => [
                'id' => $purchaseReceipt->id,
                'total' => $purchaseReceipt->total,
                'notes' => $purchaseReceipt->notes,
                'received_by' => $purchaseReceipt->receiver?->name,
                'received_at' => $purchaseReceipt->received_at?->toDateTimeString(),
                'received_at_readable' => $purchaseReceipt->received_at?->diffForHumans(),
                'posted' => $purchaseReceipt->isPosted(),
                'journal_entry_number' => $purchaseReceipt->journalEntry?->entry_number,
                'posted_at_readable' => $purchaseReceipt->posted_at?->diffForHumans(),
                'purchase_order' => $purchaseReceipt->purchaseOrder ? [
                    'id' => $purchaseReceipt->purchaseOrder->id,
                    'status' => $purchaseReceipt->purchaseOrder->status,
                    'status_label' => $purchaseReceipt->purchaseOrder->statusLabel(),
                    'total' => $purchaseReceipt->purchaseOrder->total,
                ] : null,
                'supplier' => $purchaseReceipt->purchaseOrder?->supplier ? [
                    'name' => $purchaseReceipt->purchaseOrder->supplier->name,
                    'email' => $purchaseReceipt->purchaseOrder->supplier->email,
                    'phone' => $purchaseReceipt->purchaseOrder->supplier->phone,
                ] : null,
                'items' => $purchaseReceipt->items
                    ->map(fn ($item) => [
                        'product' => $item->purchaseOrderItem?->product?->name,
                        'sku' => $item->purchaseOrderItem?->product?->sku,
                        'ordered_quantity' => $item->purchaseOrderItem?->quantity,
                        'quantity_received' => $item->quantity_received,
                        'unit_cost' => $item->purchaseOrderItem?->unit_cost,
                        'line_total' => round((float) ($item->purchaseOrderItem?->unit_cost ?? 0) * $item->quantity_received, 2),
                    ])
                    ->values(),
            ],
        ]);
    }

    public function post(PurchaseReceipt $purchaseReceipt, AccountingService $service): RedirectResponse
    {
        Gate::authorize('post', $purchaseReceipt);

        $entry = $service->postPurchaseReceipt($purchaseReceipt, auth()->user());

        return redirect()
            ->route('erp.purchasing.receipts.show', $purchaseReceipt)
            ->with('success', "Purchase receipt #{$purchaseReceipt->id} posted to accounting as {$entry->entry_number}.");
    }

    public function store(PurchaseOrder $purchaseOrder, PurchaseReceiptRequest $request, PurchasingService $service): RedirectResponse
    {
        Gate::authorize('create', PurchaseReceipt::class);

        $receipt = $service->receive(
            $purchaseOrder,
            $request->validated('items'),
            $request->validated('notes'),
            auth()->user(),
        );

        return redirect()
            ->route('erp.purchasing.receipts.show', $receipt)
            ->with('success', "Purchase receipt #{$receipt->id} recorded.");
    }
}
