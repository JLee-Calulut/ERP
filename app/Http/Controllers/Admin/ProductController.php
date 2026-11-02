<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Product::class);

        $search = trim((string) $request->query('search'));
        $categoryId = $request->query('category');

        $products = Product::query()
            ->with('category')
            ->when($search !== '', fn ($query) => $query
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")))
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'description' => $product->description,
                'category' => $product->category?->name,
                'cost_price' => $product->cost_price,
                'selling_price' => $product->selling_price,
                'stock_quantity' => $product->stock_quantity,
                'low_stock_threshold' => $product->low_stock_threshold,
                'stock_status' => $product->stockStatus(),
                'is_active' => $product->is_active,
            ]);

        return Inertia::render('Erp/Inventory/Products/Index', [
            'products' => $products,
            'categories' => $this->categoryOptions(),
            'filters' => [
                'search' => $search,
                'category' => $categoryId,
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Product::class);

        return Inertia::render('Erp/Inventory/Products/Form', [
            'product' => null,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        $product = Product::create([
            'category_id' => $request->input('category_id'),
            'name' => $request->input('name'),
            'sku' => $request->input('sku'),
            'description' => $request->input('description'),
            'cost_price' => $request->input('cost_price', 0),
            'selling_price' => $request->input('selling_price', 0),
            'stock_quantity' => $request->input('stock_quantity', 0),
            'low_stock_threshold' => $request->input('low_stock_threshold', 0),
            'is_active' => $request->boolean('is_active') || $request->input('is_active') === null,
        ]);

        return redirect()
            ->route('erp.inventory.products.index')
            ->with('success', "Product \"{$product->name}\" created.");
    }

    public function edit(Product $product): Response
    {
        Gate::authorize('update', $product);

        return Inertia::render('Erp/Inventory/Products/Form', [
            'product' => [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'name' => $product->name,
                'sku' => $product->sku,
                'description' => $product->description,
                'cost_price' => $product->cost_price,
                'selling_price' => $product->selling_price,
                'stock_quantity' => $product->stock_quantity,
                'low_stock_threshold' => $product->low_stock_threshold,
                'is_active' => $product->is_active,
            ],
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        $product->update([
            'category_id' => $request->input('category_id'),
            'name' => $request->input('name'),
            'sku' => $request->input('sku'),
            'description' => $request->input('description'),
            'cost_price' => $request->input('cost_price', 0),
            'selling_price' => $request->input('selling_price', 0),
            'stock_quantity' => $request->input('stock_quantity') ?? $product->stock_quantity,
            'low_stock_threshold' => $request->input('low_stock_threshold', 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('erp.inventory.products.index')
            ->with('success', "Product \"{$product->name}\" updated.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $product->delete();

        return redirect()
            ->route('erp.inventory.products.index')
            ->with('success', "Product \"{$product->name}\" deleted.");
    }

    private function categoryOptions(): array
    {
        return Category::orderBy('name')->get()->map(fn (Category $category) => [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
        ])->values()->all();
    }
}
