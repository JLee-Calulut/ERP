<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccountRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Account::class);

        $search = trim((string) $request->query('search'));
        $type = $request->query('type');

        $accounts = Account::query()
            ->withCount('lines')
            ->when($search !== '', fn ($query) => $query
                ->where(fn ($q) => $q
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")))
            ->when($type, fn ($query) => $query->where('type', $type))
            ->orderBy('type')
            ->orderBy('code')
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'type_label' => $account->typeLabel(),
                'is_active' => $account->is_active,
                'description' => $account->description,
                'balance' => $account->balance(),
                'lines_count' => $account->lines_count,
            ]);

        return Inertia::render('Erp/Accounting/Accounts/Index', [
            'accounts' => $accounts,
            'types' => Account::TYPES,
            'filters' => [
                'search' => $search,
                'type' => $type,
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Account::class);

        return Inertia::render('Erp/Accounting/Accounts/Form', [
            'account' => null,
        ]);
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        Gate::authorize('create', Account::class);

        $account = Account::create([
            'code' => $request->input('code'),
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'is_active' => $request->boolean('is_active') || $request->input('is_active') === null,
            'description' => $request->input('description'),
        ]);

        return redirect()
            ->route('erp.accounting.accounts.index')
            ->with('success', "Account {$account->code} \"{$account->name}\" created.");
    }

    public function edit(Account $account): Response
    {
        Gate::authorize('update', $account);

        return Inertia::render('Erp/Accounting/Accounts/Form', [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'is_active' => $account->is_active,
                'description' => $account->description,
                'lines_count' => $account->lines()->count(),
            ],
        ]);
    }

    public function update(AccountRequest $request, Account $account): RedirectResponse
    {
        Gate::authorize('update', $account);

        $account->update([
            'code' => $request->input('code'),
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'is_active' => $request->boolean('is_active'),
            'description' => $request->input('description'),
        ]);

        return redirect()
            ->route('erp.accounting.accounts.index')
            ->with('success', "Account {$account->code} \"{$account->name}\" updated.");
    }

    public function destroy(Account $account): RedirectResponse
    {
        Gate::authorize('delete', $account);

        if ($account->lines()->exists()) {
            return redirect()
                ->route('erp.accounting.accounts.index')
                ->with('error', "Account {$account->code} \"{$account->name}\" is used by journal lines and cannot be deleted.");
        }

        $account->delete();

        return redirect()
            ->route('erp.accounting.accounts.index')
            ->with('success', "Account {$account->code} \"{$account->name}\" deleted.");
    }
}
