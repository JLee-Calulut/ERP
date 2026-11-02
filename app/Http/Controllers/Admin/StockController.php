<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StockRequest;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', StockTransaction::class);

        $products = Product::query()
            ->with('category')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category?->name,
                'stock_quantity' => $product->stock_quantity,
                'low_stock_threshold' => $product->low_stock_threshold,
                'stock_status' => $product->stockStatus(),
            ]);

        $transactions = StockTransaction::with(['product', 'user'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (StockTransaction $transaction) => [
                'id' => $transaction->id,
                'product' => $transaction->product->name,
                'type' => $transaction->type,
                'type_label' => $transaction->typeLabel(),
                'quantity' => $transaction->quantity,
                'previous_quantity' => $transaction->previous_quantity,
                'new_quantity' => $transaction->new_quantity,
                'reason' => $transaction->reason,
                'user' => $transaction->user?->name,
                'created_at' => $transaction->created_at->toDateTimeString(),
                'created_at_readable' => $transaction->created_at->diffForHumans(),
            ]);

        return Inertia::render('Erp/Inventory/Stock/Index', [
            'products' => $products,
            'transactions' => $transactions,
            'types' => StockTransaction::TYPES,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', StockTransaction::class);

        $products = Product::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'stock_quantity' => $product->stock_quantity,
            ]);

        return Inertia::render('Erp/Inventory/Stock/Form', [
            'products' => $products,
            'types' => array_map(fn (string $type) => [
                'value' => $type,
                'label' => match ($type) {
                    StockTransaction::TYPE_IN => 'Receive stock (in)',
                    StockTransaction::TYPE_OUT => 'Issue stock (out)',
                    StockTransaction::TYPE_ADJUSTMENT => 'Adjust stock level',
                    default => $type,
                },
            ], StockTransaction::TYPES),
        ]);
    }

    public function store(StockRequest $request, InventoryService $service): RedirectResponse
    {
        Gate::authorize('create', StockTransaction::class);

        $product = Product::findOrFail($request->input('product_id'));

        $service->adjust(
            $product,
            $request->input('type'),
            (int) $request->input('quantity'),
            $request->input('reason'),
            auth()->user(),
        );

        return redirect()
            ->route('erp.inventory.stock.index')
            ->with('success', "Stock updated for \"{$product->name}\".");
    }
}
