<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Category::class);

        $categories = Category::withCount('products')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'products_count' => $category->products_count,
            ]);

        return Inertia::render('Erp/Inventory/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Category::class);

        return Inertia::render('Erp/Inventory/Categories/Form', [
            'category' => null,
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Gate::authorize('create', Category::class);

        $category = Category::create([
            'name' => $request->input('name'),
            'slug' => $request->filled('slug')
                ? Str::slug($request->input('slug'))
                : Str::slug($request->input('name')),
            'description' => $request->input('description'),
        ]);

        return redirect()
            ->route('erp.inventory.categories.index')
            ->with('success', "Category \"{$category->name}\" created.");
    }

    public function edit(Category $category): Response
    {
        Gate::authorize('update', $category);

        return Inertia::render('Erp/Inventory/Categories/Form', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'products_count' => $category->products()->count(),
            ],
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $category->update([
            'name' => $request->input('name'),
            'slug' => $request->filled('slug')
                ? Str::slug($request->input('slug'))
                : Str::slug($request->input('name')),
            'description' => $request->input('description'),
        ]);

        return redirect()
            ->route('erp.inventory.categories.index')
            ->with('success', "Category \"{$category->name}\" updated.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $category->delete();

        return redirect()
            ->route('erp.inventory.categories.index')
            ->with('success', "Category \"{$category->name}\" deleted.");
    }
}
