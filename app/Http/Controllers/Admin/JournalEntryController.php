<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\JournalEntryRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class JournalEntryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', JournalEntry::class);

        $entries = JournalEntry::query()
            ->with('poster')
            ->withCount('lines')
            ->withSum('lines', 'debit')
            ->latest('journal_date')
            ->latest('id')
            ->get()
            ->map(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'description' => $entry->description,
                'journal_date' => $entry->journal_date?->toDateString(),
                'journal_date_readable' => $entry->journal_date?->diffForHumans(),
                'total' => (float) $entry->lines_sum_debit,
                'lines_count' => $entry->lines_count,
                'posted_by' => $entry->poster?->name,
            ]);

        return Inertia::render('Erp/Accounting/Journal/Index', [
            'entries' => $entries,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', JournalEntry::class);

        return Inertia::render('Erp/Accounting/Journal/Form', [
            'accounts' => $this->accountOptions(),
        ]);
    }

    public function store(JournalEntryRequest $request, AccountingService $service): RedirectResponse
    {
        Gate::authorize('create', JournalEntry::class);

        $entry = $service->postJournalEntry($request->validated(), auth()->user());

        return redirect()
            ->route('erp.accounting.journal.index')
            ->with('success', "Journal entry {$entry->entry_number} posted.");
    }

    private function accountOptions(): array
    {
        return Account::where('is_active', true)
            ->orderBy('type')
            ->orderBy('code')
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'type_label' => $account->typeLabel(),
            ])
            ->values()
            ->all();
    }
}
