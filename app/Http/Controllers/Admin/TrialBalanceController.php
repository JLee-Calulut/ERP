<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\AccountingService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TrialBalanceController extends Controller
{
    public function index(AccountingService $service): Response
    {
        Gate::authorize('viewAny', Account::class);

        $data = $service->trialBalance();

        return Inertia::render('Erp/Accounting/Reports/TrialBalance', [
            'accounts' => $data['accounts'],
            'totals' => $data['totals'],
        ]);
    }
}
