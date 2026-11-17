<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GeneralLedgerController extends Controller
{
    public function index(Request $request, AccountingService $service): Response
    {
        Gate::authorize('viewAny', JournalEntry::class);

        $accountId = $request->integer('account');
        $selectedAccount = null;

        if ($accountId) {
            $account = Account::find($accountId);

            if (! $account) {
                abort(404);
            }

            $selectedAccount = [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type_label' => $account->typeLabel(),
                'balance' => $account->balance(),
            ];
        }

        $ledger = $service->generalLedger($accountId ?: null);

        return Inertia::render('Erp/Accounting/Reports/GeneralLedger', [
            'accounts' => $ledger['accounts'],
            'postings' => $ledger['postings'],
            'selectedAccount' => $selectedAccount,
            'filters' => [
                'account' => $accountId ?: null,
            ],
        ]);
    }
}
