<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Customer::class);

        $search = trim((string) $request->query('search'));

        $customers = Customer::query()
            ->withCount('salesOrders')
            ->when($search !== '', fn ($query) => $query
                ->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")))
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'city' => $customer->address_city,
                'country' => $customer->address_country,
                'orders_count' => $customer->sales_orders_count,
            ]);

        return Inertia::render('Erp/Sales/Customers/Index', [
            'customers' => $customers,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Customer::class);

        return Inertia::render('Erp/Sales/Customers/Form', [
            'customer' => null,
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        Gate::authorize('create', Customer::class);

        $customer = Customer::create($request->validated());

        return redirect()
            ->route('erp.sales.customers.index')
            ->with('success', "Customer \"{$customer->name}\" created successfully.");
    }

    public function edit(Customer $customer): Response
    {
        Gate::authorize('update', $customer);

        return Inertia::render('Erp/Sales/Customers/Form', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address_line1' => $customer->address_line1,
                'address_city' => $customer->address_city,
                'address_state' => $customer->address_state,
                'address_postal_code' => $customer->address_postal_code,
                'address_country' => $customer->address_country,
            ],
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('update', $customer);

        $customer->update($request->validated());

        return redirect()
            ->route('erp.sales.customers.index')
            ->with('success', "Customer \"{$customer->name}\" updated successfully.");
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $customer->delete();

        return redirect()
            ->route('erp.sales.customers.index')
            ->with('success', "Customer \"{$customer->name}\" deleted successfully.");
    }
}
