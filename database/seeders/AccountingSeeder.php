<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\PurchaseReceipt;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Database\Seeder;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $accounting = app(AccountingService::class);
        $admin = User::where('email', 'admin@erp.test')->first();

        // ------------------------------------------------------------------
        // Chart of accounts: one row per account type is present so the
        // journal picker and the ledger demo cover every classification.
        // ------------------------------------------------------------------
        // Accounts are resolved by their stable chart code (firstOrCreate),
        // so re-running the seeder never duplicates rows and the Sales /
        // Purchasing integration (which posts against these codes) always
        // finds the Accounts Receivable, Inventory and Accounts Payable
        // accounts it needs.
        $accounts = collect([
            ['1010', 'Cash', 'asset', 'Cash on hand and in bank accounts.'],
            ['1020', 'Accounts Receivable', 'asset', 'Amounts owed to the business by customers.'],
            ['1030', 'Inventory', 'asset', 'Products held for sale.'],
            ['1040', 'Office Equipment', 'asset', 'Furniture and equipment used in operations.'],
            ['2010', 'Accounts Payable', 'liability', 'Amounts the business owes to suppliers.'],
            ['2020', 'Accrued Expenses', 'liability', 'Expenses incurred but not yet paid.'],
            ['3010', "Owner's Equity", 'equity', 'Owner contributions and retained value.'],
            ['3020', 'Retained Earnings', 'equity', 'Cumulative profits kept in the business.'],
            ['4010', 'Sales Revenue', 'revenue', 'Revenue from product sales.'],
            ['4020', 'Other Income', 'revenue', 'Non-operating income.'],
            ['5010', 'Cost of Goods Sold', 'expense', 'Direct costs of products sold.'],
            ['5020', 'Salaries Expense', 'expense', 'Employee wages and salaries.'],
            ['5030', 'Rent Expense', 'expense', 'Facility rent.'],
            ['5040', 'Utilities Expense', 'expense', 'Electricity, water and internet.'],
            ['5050', 'Marketing Expense', 'expense', 'Advertising and promotions.'],
            ['5060', 'Office Supplies Expense', 'expense', 'Stationery and consumables.'],
        ])->mapWithKeys(fn (array $data) => [
            $data[0] => Account::firstOrCreate(
                ['code' => $data[0]],
                [
                    'name' => $data[1],
                    'type' => $data[2],
                    'description' => $data[3],
                    'is_active' => true,
                ]
            ),
        ]);

        $line = fn (string $code, float $debit = 0, float $credit = 0, ?string $memo = null) => [
            'account_id' => $accounts[$code]->id,
            'debit' => $debit,
            'credit' => $credit,
            'memo' => $memo,
        ];

        // ------------------------------------------------------------------
        // Balanced sample entries, consistent with the seeded catalog: a
        // capital injection, inventory bought on account (mirrors Purchasing),
        // customer sales on account (mirrors Sales), plus a spread of dated
        // operating entries so the trial balance and general ledger show
        // meaningful history across every account type. Each entry is balanced
        // and posted through AccountingService so the ledger stays the single
        // source of truth.
        // ------------------------------------------------------------------
        $entries = [
            [
                'date' => now()->subMonths(3)->startOfMonth(),
                'description' => 'Owner capital contribution',
                'lines' => [
                    $line('1010', 50000, 0, 'Capital contribution'),
                    $line('3010', 0, 50000, 'Owner contribution'),
                ],
            ],
            [
                'date' => now()->subMonths(2)->startOfMonth(),
                'description' => 'Purchased inventory on account',
                'lines' => [
                    $line('1030', 20000, 0, 'Inventory from suppliers'),
                    $line('2010', 0, 20000, 'Payable to suppliers'),
                ],
            ],
            [
                'date' => now()->subMonth()->startOfMonth(),
                'description' => 'Recorded customer sales on account',
                'lines' => [
                    $line('1020', 12500, 0, 'Customer receivables'),
                    $line('4010', 0, 12500, 'Sales revenue'),
                ],
            ],
            [
                'date' => now()->subDays(80),
                'description' => 'Paid facility rent in cash',
                'lines' => [
                    $line('5030', 3000, 0, 'Rent for the month'),
                    $line('1010', 0, 3000, 'Cash payment'),
                ],
            ],
            [
                'date' => now()->subDays(65),
                'description' => 'Accrued staff salaries',
                'lines' => [
                    $line('5020', 8500, 0, 'Monthly salaries'),
                    $line('2020', 0, 8500, 'Accrued salaries payable'),
                ],
            ],
            [
                'date' => now()->subDays(50),
                'description' => 'Recognized cost of goods sold',
                'lines' => [
                    $line('5010', 6000, 0, 'Cost of goods sold'),
                    $line('1030', 0, 6000, 'Inventory released'),
                ],
            ],
            [
                'date' => now()->subDays(40),
                'description' => 'Collected customer receivables',
                'lines' => [
                    $line('1010', 7500, 0, 'Cash received from customers'),
                    $line('1020', 0, 7500, 'Receivables settled'),
                ],
            ],
            [
                'date' => now()->subDays(30),
                'description' => 'Paid suppliers on account',
                'lines' => [
                    $line('2010', 9000, 0, 'Payment to suppliers'),
                    $line('1010', 0, 9000, 'Cash payment'),
                ],
            ],
            [
                'date' => now()->subDays(20),
                'description' => 'Purchased office equipment on account',
                'lines' => [
                    $line('1040', 4500, 0, 'Office equipment'),
                    $line('2010', 0, 4500, 'Payable to supplier'),
                ],
            ],
            [
                'date' => now()->subDays(12),
                'description' => 'Paid utilities expense',
                'lines' => [
                    $line('5040', 650, 0, 'Utilities for the month'),
                    $line('1010', 0, 650, 'Cash payment'),
                ],
            ],
            [
                'date' => now()->subDays(6),
                'description' => 'Incurred marketing expense on account',
                'lines' => [
                    $line('5050', 1200, 0, 'Marketing campaign'),
                    $line('2010', 0, 1200, 'Payable to supplier'),
                ],
            ],
            [
                'date' => now()->subDays(3),
                'description' => 'Received other income',
                'lines' => [
                    $line('1010', 800, 0, 'Cash received'),
                    $line('4020', 0, 800, 'Other income'),
                ],
            ],
        ];

        foreach ($entries as $entry) {
            $accounting->postJournalEntry([
                'journal_date' => $entry['date']->toDateString(),
                'description' => $entry['description'],
                'lines' => $entry['lines'],
            ], $admin);
        }

        // ------------------------------------------------------------------
        // Post the seeded operational documents to accounting (Step 7 Part 3)
        // so the ledger, trial balance and dashboard reflect the real history
        // from the Sales and Purchasing modules. The two most recent seeded
        // invoices and the seeded TechSource receipt are posted through the
        // same AccountingService methods the detail-page buttons call — the
        // receipts post last so it is the newest activity in the dashboard
        // feed. Re-running the seeder is safe: each document's one-time guard
        // rejects a second posting, and firstOrCreate keeps the chart stable.
        // ------------------------------------------------------------------
        $invoices = Invoice::query()
            ->orderByDesc('issued_at')
            ->take(2)
            ->get()
            ->reverse();

        foreach ($invoices as $invoice) {
            $accounting->postInvoice($invoice, $admin);
        }

        $receipt = PurchaseReceipt::query()->orderBy('id')->first();
        if ($receipt) {
            $accounting->postPurchaseReceipt($receipt, $admin);
        }
    }
}
