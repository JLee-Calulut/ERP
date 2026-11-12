<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Supplier::class);

        $search = trim((string) $request->query('search'));

        $suppliers = Supplier::query()
            ->withCount('purchaseOrders')
            ->when($search !== '', fn ($query) => $query
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")))
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'city' => $supplier->address_city,
                'country' => $supplier->address_country,
                'orders_count' => $supplier->purchase_orders_count,
            ]);

        return Inertia::render('Erp/Purchasing/Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Supplier::class);

        return Inertia::render('Erp/Purchasing/Suppliers/Form', [
            'supplier' => null,
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        Gate::authorize('create', Supplier::class);

        $supplier = Supplier::create($request->validated());

        return redirect()
            ->route('erp.purchasing.suppliers.index')
            ->with('success', "Supplier \"{$supplier->name}\" created.");
    }

    public function edit(Supplier $supplier): Response
    {
        Gate::authorize('update', $supplier);

        return Inertia::render('Erp/Purchasing/Suppliers/Form', [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'address_line1' => $supplier->address_line1,
                'address_city' => $supplier->address_city,
                'address_state' => $supplier->address_state,
                'address_postal_code' => $supplier->address_postal_code,
                'address_country' => $supplier->address_country,
            ],
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        Gate::authorize('update', $supplier);

        $supplier->update($request->validated());

        return redirect()
            ->route('erp.purchasing.suppliers.index')
            ->with('success', "Supplier \"{$supplier->name}\" updated.");
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        Gate::authorize('delete', $supplier);

        $supplier->delete();

        return redirect()
            ->route('erp.purchasing.suppliers.index')
            ->with('success', "Supplier \"{$supplier->name}\" deleted.");
    }
}
