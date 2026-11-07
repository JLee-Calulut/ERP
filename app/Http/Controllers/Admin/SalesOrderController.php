<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SalesOrderRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Services\SalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrderController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SalesOrder::class);

        $search = trim((string) $request->query('search'));
        $status = $request->query('status');

        $orders = SalesOrder::query()
            ->with('customer')
            ->withCount('items')
            ->when($search !== '', fn ($query) => $query
                ->whereHas('customer', fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('ordered_at')
            ->get()
            ->map(fn (SalesOrder $order) => [
                'id' => $order->id,
                'customer' => $order->customer?->name,
                'status' => $order->status,
                'status_label' => $order->statusLabel(),
                'total' => $order->total,
                'items_count' => $order->items_count,
                'ordered_at' => $order->ordered_at?->toDateString(),
                'ordered_at_readable' => $order->ordered_at?->diffForHumans(),
            ]);

        return Inertia::render('Erp/Sales/Orders/Index', [
            'orders' => $orders,
            'statuses' => SalesOrder::STATUSES,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', SalesOrder::class);

        return Inertia::render('Erp/Sales/Orders/Form', [
            'customers' => $this->customerOptions(),
            'products' => $this->productOptions(),
        ]);
    }

    public function store(SalesOrderRequest $request, SalesService $service): RedirectResponse
    {
        Gate::authorize('create', SalesOrder::class);

        $order = $service->createOrder($request->validated(), auth()->user());

        return redirect()
            ->route('erp.sales.orders.show', $order)
            ->with('success', "Sales order #{$order->id} created.");
    }

    public function show(SalesOrder $order): Response
    {
        Gate::authorize('view', $order);

        return Inertia::render('Erp/Sales/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'status_label' => $order->statusLabel(),
                'subtotal' => $order->subtotal,
                'total' => $order->total,
                'ordered_at' => $order->ordered_at?->toDateTimeString(),
                'ordered_at_readable' => $order->ordered_at?->diffForHumans(),
                'customer' => $order->customer ? [
                    'id' => $order->customer->id,
                    'name' => $order->customer->name,
                    'email' => $order->customer->email,
                    'phone' => $order->customer->phone,
                ] : null,
                'items' => $order->items
                    ->map(fn ($item) => [
                        'id' => $item->id,
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'sku' => $item->product->sku,
                        ] : null,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'line_total' => $item->line_total,
                    ])
                    ->values(),
                'invoice' => $order->invoice ? [
                    'id' => $order->invoice->id,
                    'invoice_number' => $order->invoice->invoice_number,
                    'status' => $order->invoice->status,
                    'status_label' => $order->invoice->statusLabel(),
                    'total' => $order->invoice->total,
                ] : null,
                'available_transitions' => array_values(SalesOrder::TRANSITIONS[$order->status] ?? []),
            ],
        ]);
    }

    public function updateStatus(Request $request, SalesOrder $order, SalesService $service): RedirectResponse
    {
        Gate::authorize('updateStatus', $order);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', SalesOrder::STATUSES)],
        ]);

        $service->transitionStatus($order, $data['status']);

        return redirect()
            ->route('erp.sales.orders.show', $order)
            ->with('success', "Sales order #{$order->id} is now {$order->statusLabel()}.");
    }

    private function customerOptions(): array
    {
        return Customer::orderBy('name')
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
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
                'selling_price' => $product->selling_price,
                'stock_quantity' => $product->stock_quantity,
            ])
            ->values()
            ->all();
    }
}
