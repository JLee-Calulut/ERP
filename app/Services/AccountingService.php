<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PurchaseReceipt;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    /**
     * Stable chart codes that the Sales and Purchasing integration posts
     * against. Resolving these accounts by code (not by id) keeps the
     * mapping robust — the chart can be re-ordered or edited freely as long
     * as these codes exist and stay active.
     */
    public const ACCOUNT_CASH = '1010';

    public const ACCOUNT_ACCOUNTS_RECEIVABLE = '1020';

    public const ACCOUNT_INVENTORY = '1030';

    public const ACCOUNT_ACCOUNTS_PAYABLE = '2010';

    public const ACCOUNT_SALES_REVENUE = '4010';

    /**
     * Resolve an active account by its stable chart code.
     *
     * @throws ValidationException when the account is missing or inactive.
     */
    public function accountByCode(string $code): Account
    {
        /** @var Account|null $account */
        $account = Account::query()->where('code', $code)->first();

        if (! $account || ! $account->is_active) {
            throw ValidationException::withMessages([
                'account' => "The account with code \"{$code}\" is missing or inactive. Posting to accounting is unavailable until it exists and is active.",
            ]);
        }

        return $account;
    }

    /**
     * Post a sales invoice to accounting: debit Accounts Receivable and
     * credit Sales Revenue for the invoice total, as one balanced entry that
     * references the invoice in its description.
     *
     * The row is locked and the one-time guard is enforced inside the same
     * transaction as the posting, so an already-posted invoice can never be
     * posted twice — even under concurrency.
     *
     * @throws ValidationException when the invoice is already posted or the
     *                             required accounts are missing/inactive.
     */
    public function postInvoice(Invoice $invoice, ?User $user = null): JournalEntry
    {
        return DB::transaction(function () use ($invoice, $user) {
            /** @var Invoice $locked */
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($locked->isPosted()) {
                throw ValidationException::withMessages([
                    'invoice' => "Invoice {$invoice->invoice_number} has already been posted to accounting.",
                ]);
            }

            $invoice->loadMissing('salesOrder.customer');
            $customerName = $invoice->salesOrder?->customer?->name ?? 'Customer';

            $entry = $this->postJournalEntry([
                'journal_date' => $invoice->issued_at?->toDateString() ?? now()->toDateString(),
                'description' => "Sales invoice {$invoice->invoice_number}",
                'lines' => [
                    [
                        'account_id' => $this->accountByCode(self::ACCOUNT_ACCOUNTS_RECEIVABLE)->id,
                        'debit' => $invoice->total,
                        'credit' => 0,
                        'memo' => "Receivable from {$customerName}",
                    ],
                    [
                        'account_id' => $this->accountByCode(self::ACCOUNT_SALES_REVENUE)->id,
                        'debit' => 0,
                        'credit' => $invoice->total,
                        'memo' => 'Sales revenue',
                    ],
                ],
            ], $user);

            $locked->forceFill([
                'journal_entry_id' => $entry->id,
                'posted_at' => now(),
            ])->save();

            return $entry;
        });
    }

    /**
     * Post a purchase receipt to accounting: debit Inventory and credit
     * Accounts Payable for the receipt total, as one balanced entry whose
     * description references the supplier and the receipt.
     *
     * The row is locked and the one-time guard is enforced inside the same
     * transaction as the posting, so an already-posted receipt can never be
     * posted twice.
     *
     * @throws ValidationException when the receipt is already posted or the
     *                             required accounts are missing/inactive.
     */
    public function postPurchaseReceipt(PurchaseReceipt $receipt, ?User $user = null): JournalEntry
    {
        return DB::transaction(function () use ($receipt, $user) {
            /** @var PurchaseReceipt $locked */
            $locked = PurchaseReceipt::query()->lockForUpdate()->findOrFail($receipt->id);

            if ($locked->isPosted()) {
                throw ValidationException::withMessages([
                    'receipt' => "Purchase receipt #{$receipt->id} has already been posted to accounting.",
                ]);
            }

            $receipt->loadMissing('purchaseOrder.supplier');
            $supplierName = $receipt->purchaseOrder?->supplier?->name ?? 'Supplier';

            $entry = $this->postJournalEntry([
                'journal_date' => $receipt->received_at?->toDateString() ?? now()->toDateString(),
                'description' => "Purchase receipt #{$receipt->id} — {$supplierName}",
                'lines' => [
                    [
                        'account_id' => $this->accountByCode(self::ACCOUNT_INVENTORY)->id,
                        'debit' => $receipt->total,
                        'credit' => 0,
                        'memo' => 'Inventory received',
                    ],
                    [
                        'account_id' => $this->accountByCode(self::ACCOUNT_ACCOUNTS_PAYABLE)->id,
                        'debit' => 0,
                        'credit' => $receipt->total,
                        'memo' => "Payable to {$supplierName}",
                    ],
                ],
            ], $user);

            $locked->forceFill([
                'journal_entry_id' => $entry->id,
                'posted_at' => now(),
            ])->save();

            return $entry;
        });
    }

    /**
     * Post a double-entry journal entry atomically.
     *
     * All persistence happens inside one DB transaction so a failed entry
     * never leaves a partial journal. The entry must have at least two lines,
     * each line must reference a valid, active account, and the summed debits
     * must equal the summed credits (and be greater than zero).
     *
     * The entry number (JE- padded to six digits) is derived from the row id
     * inside the transaction, mirroring SalesService's INV- pattern, so it
     * stays unique even when entries are posted concurrently.
     *
     * @param  array{journal_date: string, description: string, lines: array<int, array{account_id: int, debit: float, credit: float, memo?: string|null}>}  $data
     *
     * @throws ValidationException when the entry is unbalanced or references
     *                             an unknown/inactive account.
     */
    public function postJournalEntry(array $data, ?User $user = null): JournalEntry
    {
        return DB::transaction(function () use ($data, $user) {
            $lines = collect($data['lines']);

            if ($lines->count() < 2) {
                throw ValidationException::withMessages([
                    'lines' => 'A journal entry needs at least two lines.',
                ]);
            }

            /** @var Collection<int, Account> $accounts */
            $accounts = Account::query()
                ->whereIn('id', $lines->pluck('account_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $totalDebit = 0.0;
            $totalCredit = 0.0;

            foreach ($lines as $index => $line) {
                /* @var Account|null $account */
                $account = $accounts->get($line['account_id']);

                if (! $account || ! $account->is_active) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.account_id" => 'The selected account is invalid or inactive.',
                    ]);
                }

                $totalDebit += (float) $line['debit'];
                $totalCredit += (float) $line['credit'];
            }

            if ($totalDebit <= 0 || abs($totalDebit - $totalCredit) > 0.005) {
                throw ValidationException::withMessages([
                    'lines' => 'The journal entry must be balanced: debits must equal credits and be greater than zero.',
                ]);
            }

            /** @var JournalEntry $entry */
            $entry = JournalEntry::create([
                'entry_number' => null,
                'journal_date' => $data['journal_date'],
                'description' => $data['description'],
                'posted_by' => $user?->id,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => round((float) $line['debit'], 2),
                    'credit' => round((float) $line['credit'], 2),
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            $entry->forceFill([
                'entry_number' => 'JE-'.str_pad((string) $entry->id, 6, '0', STR_PAD_LEFT),
            ])->save();

            return $entry;
        });
    }

    /**
     * Build the trial balance: every account with the debit/credit totals of
     * its journal lines, its derived balance (credits minus debits, matching
     * Account::balance()) and a summary that confirms the ledger balances.
     *
     * Balances are never stored — they are recomputed from the journal lines,
     * which remain the single source of truth.
     *
     * @return array{accounts: array<int, array<string, mixed>>, totals: array{debit: float, credit: float, balanced: bool}}
     */
    public function trialBalance(): array
    {
        $accounts = Account::query()
            ->orderBy('type')
            ->orderBy('code')
            ->get()
            ->map(function (Account $account) {
                $debitTotal = (float) $account->lines()->sum('debit');
                $creditTotal = (float) $account->lines()->sum('credit');

                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'type_label' => $account->typeLabel(),
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'balance' => $account->balance(),
                ];
            })
            ->values()
            ->all();

        $totalDebits = (float) collect($accounts)->sum('debit_total');
        $totalCredits = (float) collect($accounts)->sum('credit_total');

        return [
            'accounts' => $accounts,
            'totals' => [
                'debit' => $totalDebits,
                'credit' => $totalCredits,
                'balanced' => abs($totalDebits - $totalCredits) < 0.01,
            ],
        ];
    }

    /**
     * Build the general ledger: per-account transaction history with a
     * running balance after every posting.
     *
     * Postings are ordered by journal date (then entry id, then line id) and
     * the running balance is tracked per account using the same credits minus
     * debits convention as Account::balance(). When an account id is given,
     * only that account's postings are returned; otherwise every posting is
     * returned and each row carries its account.
     *
     * @return array{accounts: array<int, array<string, mixed>>, selectedAccountId: int|null, postings: array<int, array<string, mixed>>}
     */
    public function generalLedger(?int $accountId = null): array
    {
        $accounts = Account::query()
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

        $entries = JournalEntry::query()
            ->with(['lines' => function ($query) use ($accountId) {
                $query->when($accountId, fn ($q) => $q->where('journal_entry_lines.account_id', $accountId))
                    ->with('account')
                    ->orderBy('id');
            }])
            ->orderBy('journal_date')
            ->orderBy('id')
            ->get();

        $runningBalances = [];
        $postings = [];

        foreach ($entries as $entry) {
            foreach ($entry->lines as $line) {
                $runningBalances[$line->account_id] =
                    ($runningBalances[$line->account_id] ?? 0) + $line->credit - $line->debit;

                $postings[] = [
                    'id' => $line->id,
                    'account_id' => $line->account_id,
                    'account_code' => $line->account?->code,
                    'account_name' => $line->account?->name,
                    'journal_date' => $entry->journal_date?->toDateString(),
                    'entry_number' => $entry->entry_number,
                    'description' => $entry->description,
                    'memo' => $line->memo,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                    'balance' => round($runningBalances[$line->account_id], 2),
                ];
            }
        }

        return [
            'accounts' => $accounts,
            'selectedAccountId' => $accountId,
            'postings' => $postings,
        ];
    }

    /**
     * Net income derived from live account balances: revenue minus expenses.
     *
     * Under the credits-minus-debits convention used by Account::balance(),
     * revenue accounts carry positive balances and expense accounts carry
     * negative ones, so summing every revenue and expense balance equals
     * revenue minus expenses. Like every balance figure, the value is
     * recomputed from the journal lines on each call — nothing is stored —
     * so posting an invoice or a new entry moves the KPI immediately.
     */
    public function netIncome(): float
    {
        return (float) Account::query()
            ->whereIn('type', [Account::TYPE_REVENUE, Account::TYPE_EXPENSE])
            ->get()
            ->sum(fn (Account $account) => $account->balance());
    }
}
