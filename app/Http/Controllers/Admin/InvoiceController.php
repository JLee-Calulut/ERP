<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Services\AccountingService;
use App\Services\SalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $invoices = Invoice::with('salesOrder.customer')
            ->latest('issued_at')
            ->get()
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'order_id' => $invoice->salesOrder?->id,
                'customer' => $invoice->salesOrder?->customer?->name,
                'total' => $invoice->total,
                'status' => $invoice->status,
                'status_label' => $invoice->statusLabel(),
                'issued_at' => $invoice->issued_at?->toDateString(),
                'issued_at_readable' => $invoice->issued_at?->diffForHumans(),
            ]);

        return Inertia::render('Erp/Sales/Invoices/Index', [
            'invoices' => $invoices,
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        $invoice->load('salesOrder.customer', 'salesOrder.items.product', 'journalEntry');

        return Inertia::render('Erp/Sales/Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total' => $invoice->total,
                'status' => $invoice->status,
                'status_label' => $invoice->statusLabel(),
                'issued_at' => $invoice->issued_at?->toDateTimeString(),
                'issued_at_readable' => $invoice->issued_at?->diffForHumans(),
                'posted' => $invoice->isPosted(),
                'journal_entry_number' => $invoice->journalEntry?->entry_number,
                'posted_at_readable' => $invoice->posted_at?->diffForHumans(),
                'order' => $invoice->salesOrder ? [
                    'id' => $invoice->salesOrder->id,
                    'status_label' => $invoice->salesOrder->statusLabel(),
                ] : null,
                'customer' => $invoice->salesOrder?->customer ? [
                    'name' => $invoice->salesOrder->customer->name,
                    'email' => $invoice->salesOrder->customer->email,
                    'phone' => $invoice->salesOrder->customer->phone,
                ] : null,
                'items' => $invoice->salesOrder?->items
                    ->map(fn ($item) => [
                        'product' => $item->product?->name,
                        'sku' => $item->product?->sku,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'line_total' => $item->line_total,
                    ])
                    ->values() ?? [],
            ],
        ]);
    }

    public function post(Invoice $invoice, AccountingService $service): RedirectResponse
    {
        Gate::authorize('post', $invoice);

        $entry = $service->postInvoice($invoice, auth()->user());

        return redirect()
            ->route('erp.sales.invoices.show', $invoice)
            ->with('success', "Invoice {$invoice->invoice_number} posted to accounting as {$entry->entry_number}.");
    }

    public function store(SalesOrder $order, SalesService $service): RedirectResponse
    {
        Gate::authorize('create', Invoice::class);

        $invoice = $service->generateInvoice($order);

        return redirect()
            ->route('erp.sales.invoices.show', $invoice)
            ->with('success', "Invoice {$invoice->invoice_number} issued.");
    }
}
