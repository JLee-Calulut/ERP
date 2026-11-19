<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\ReportSnapshot;
use App\Models\SalesOrder;
use App\Models\StockTransaction;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ReportingService
{
    /**
     * Generate a report for the given snapshot: compute the payload from the
     * live tables at the time the job runs, persist it on the snapshot and
     * move the snapshot to completed.
     *
     * Every report type has one deterministic method here (sales() and
     * inventory()); an unknown type is rejected. Computed output is stored
     * only on the snapshot — the source tables are read, never written.
     *
     * @throws InvalidArgumentException when the snapshot type is unsupported.
     */
    public function generate(ReportSnapshot $snapshot): array
    {
        $payload = match ($snapshot->type) {
            ReportSnapshot::TYPE_SALES => $this->sales($snapshot->parameters ?: []),
            ReportSnapshot::TYPE_INVENTORY => $this->inventory($snapshot->parameters ?: []),
            ReportSnapshot::TYPE_INCOME_STATEMENT => $this->incomeStatement($snapshot->parameters ?: []),
            ReportSnapshot::TYPE_BALANCE_SHEET => $this->balanceSheet($snapshot->parameters ?: []),
            ReportSnapshot::TYPE_PURCHASING => $this->purchasing($snapshot->parameters ?: []),
            default => throw new InvalidArgumentException(
                "Unsupported report type [{$snapshot->type}]."
            ),
        };

        $snapshot->markCompleted($payload);

        return $payload;
    }

    /**
     * Sales report: revenue by month, top products by revenue, top customers,
     * invoice count and average order value.
     *
     * The figures are derived from live data at generation time — revenue
     * comes from completed orders, the invoice count from the invoices table,
     * and products/customers are ranked by the line totals of those orders.
     * An optional date range narrows the window; with no parameters every
     * completed order is included.
     *
     * @param  array{date_from?: string|null, date_to?: string|null}  $parameters
     * @return array<string, mixed>
     */
    public function sales(array $parameters = []): array
    {
        $from = $parameters['date_from'] ?? null;
        $to = $parameters['date_to'] ?? null;

        /** @var Collection<int, SalesOrder> $orders */
        $orders = SalesOrder::query()
            ->where('status', SalesOrder::STATUS_COMPLETED)
            ->when($from, fn ($query) => $query->whereDate('ordered_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('ordered_at', '<=', $to))
            ->with(['items.product', 'customer'])
            ->orderBy('ordered_at')
            ->get();

        $totalRevenue = round((float) $orders->sum('total'), 2);
        $orderCount = $orders->count();
        $averageOrderValue = $orderCount > 0 ? round($totalRevenue / $orderCount, 2) : 0;

        $invoiceQuery = Invoice::query();
        if ($from) {
            $invoiceQuery->whereDate('issued_at', '>=', $from);
        }
        if ($to) {
            $invoiceQuery->whereDate('issued_at', '<=', $to);
        }

        $revenueByMonth = $orders
            ->groupBy(fn (SalesOrder $order) => $order->ordered_at->format('Y-m'))
            ->map(fn ($monthOrders, string $month) => [
                'month' => $month,
                'label' => Carbon::parse($month)->format('M Y'),
                'revenue' => round((float) $monthOrders->sum('total'), 2),
            ])
            ->sortBy('month')
            ->values()
            ->all();

        $ranked = function (Collection $items, string $revenueKey): array {
            return $items
                ->map(fn ($item) => $item + [
                    'revenue' => round((float) $item['revenue'], 2),
                ])
                ->sort(fn (array $a, array $b) => $b['revenue'] <=> $a['revenue']
                    ?: strcmp((string) $a['name'], (string) $b['name']))
                ->take(5)
                ->values()
                ->all();
        };

        $topProducts = $ranked(
            $orders
                ->flatMap(fn (SalesOrder $order) => $order->items)
                ->groupBy('product_id')
                ->map(function ($items, $productId) {
                    $product = $items->first()->product;

                    return [
                        'product_id' => (int) $productId,
                        'name' => $product?->name ?? 'Product #'.$productId,
                        'quantity' => (int) $items->sum('quantity'),
                        'revenue' => (float) $items->sum('line_total'),
                    ];
                })
                ->values(),
            'revenue',
        );

        $topCustomers = $ranked(
            $orders
                ->groupBy('customer_id')
                ->map(function ($customerOrders) {
                    $customer = $customerOrders->first()->customer;

                    return [
                        'customer_id' => (int) $customerOrders->first()->customer_id,
                        'name' => $customer?->name ?? 'Customer',
                        'order_count' => $customerOrders->count(),
                        'revenue' => (float) $customerOrders->sum('total'),
                    ];
                })
                ->values(),
            'revenue',
        );

        return [
            'type' => ReportSnapshot::TYPE_SALES,
            'generated_at' => now()->toDateTimeString(),
            'summary' => [
                'total_revenue' => $totalRevenue,
                'order_count' => $orderCount,
                'average_order_value' => $averageOrderValue,
                'invoice_count' => (int) $invoiceQuery->count(),
            ],
            'revenue_by_month' => $revenueByMonth,
            'top_products' => $topProducts,
            'top_customers' => $topCustomers,
        ];
    }

    /**
     * Inventory report: stock valuation by category, low-stock list and
     * in/out movement counts from the stock ledger.
     *
     * Valuation is stock_quantity x cost_price per product, summed by
     * category. The low-stock list reuses Product::isLowStock() so it stays
     * consistent with the dashboard. Movement counts are tallied from the
     * stock_transactions ledger by type.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function inventory(array $parameters = []): array
    {
        /** @var Collection<int, Product> $products */
        $products = Product::query()
            ->with('category')
            ->orderBy('name')
            ->get();

        $valuationByCategory = $products
            ->groupBy(fn (Product $product) => $product->category_id)
            ->map(function ($categoryProducts, $categoryId) {
                $category = $categoryProducts->first()->category;

                return [
                    'category_id' => $category?->id,
                    'category' => $category?->name ?? 'Uncategorized',
                    'product_count' => $categoryProducts->count(),
                    'units' => (int) $categoryProducts->sum('stock_quantity'),
                    'valuation' => round(
                        (float) $categoryProducts->sum(
                            fn (Product $product) => $product->stock_quantity * $product->cost_price
                        ),
                        2,
                    ),
                ];
            })
            ->sortBy('category')
            ->values()
            ->all();

        $lowStock = $products
            ->filter(fn (Product $product) => $product->isLowStock())
            ->map(fn (Product $product) => [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category?->name ?? 'Uncategorized',
                'stock_quantity' => (int) $product->stock_quantity,
                'low_stock_threshold' => (int) $product->low_stock_threshold,
                'status' => $product->stockStatus(),
            ])
            ->values()
            ->all();

        $movementTotals = StockTransaction::query()
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->all();

        $movements = [
            'in' => (int) ($movementTotals[StockTransaction::TYPE_IN] ?? 0),
            'out' => (int) ($movementTotals[StockTransaction::TYPE_OUT] ?? 0),
            'adjustment' => (int) ($movementTotals[StockTransaction::TYPE_ADJUSTMENT] ?? 0),
        ];

        return [
            'type' => ReportSnapshot::TYPE_INVENTORY,
            'generated_at' => now()->toDateTimeString(),
            'summary' => [
                'product_count' => $products->count(),
                'total_valuation' => round(
                    (float) $products->sum(
                        fn (Product $product) => $product->stock_quantity * $product->cost_price
                    ),
                    2,
                ),
                'low_stock_count' => count($lowStock),
                'movement_count' => array_sum($movements),
            ],
            'valuation_by_category' => $valuationByCategory,
            'low_stock' => $lowStock,
            'movements' => $movements,
        ];
    }

    /**
     * Income statement: revenue and expense accounts grouped into their two
     * sections with subtotals and a net income total.
     *
     * Every figure is derived from the live journal lines at generation time
     * using the same credits-minus-debits convention as Account::balance()
     * and AccountingService::netIncome() — nothing is stored, and the full
     * ledger reconciles to the dashboard Net Income figure. Revenue accounts
     * carry positive balances and expense accounts carry negative ones, so
     * each expense is shown as the positive magnitude of its balance and net
     * income is total revenue minus total expenses. An optional date range
     * narrows the window to the journal entries dated inside it, and the
     * statement still reconciles because every entry in the range balances.
     *
     * @param  array{date_from?: string|null, date_to?: string|null}  $parameters
     * @return array<string, mixed>
     */
    public function incomeStatement(array $parameters = []): array
    {
        $balances = $this->accountBalancesWithinRange($parameters);

        $revenue = $balances
            ->filter(fn (array $account) => $account['type'] === Account::TYPE_REVENUE)
            ->map(fn (array $account) => [
                'account_id' => $account['id'],
                'code' => $account['code'],
                'name' => $account['name'],
                'balance' => $account['balance'],
                'amount' => $account['balance'],
            ])
            ->sortBy('code')
            ->values()
            ->all();

        $expenses = $balances
            ->filter(fn (array $account) => $account['type'] === Account::TYPE_EXPENSE)
            ->map(fn (array $account) => [
                'account_id' => $account['id'],
                'code' => $account['code'],
                'name' => $account['name'],
                'balance' => $account['balance'],
                'amount' => round(-(float) $account['balance'], 2),
            ])
            ->sortBy('code')
            ->values()
            ->all();

        $totalRevenue = round((float) collect($revenue)->sum('amount'), 2);
        $totalExpenses = round((float) collect($expenses)->sum('amount'), 2);

        return [
            'type' => ReportSnapshot::TYPE_INCOME_STATEMENT,
            'generated_at' => now()->toDateTimeString(),
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_expenses' => $totalExpenses,
                'net_income' => round($totalRevenue - $totalExpenses, 2),
            ],
            'revenue' => $revenue,
            'expenses' => $expenses,
            'totals' => [
                'revenue' => $totalRevenue,
                'expenses' => $totalExpenses,
            ],
        ];
    }

    /**
     * Balance sheet: assets, liabilities and equity grouped with totals, with
     * retained earnings rolled up from the same net-income figure used by
     * AccountingService::netIncome() so the sheet balances.
     *
     * Assets and liabilities carry debit/credit-normal amounts derived from
     * the live journal lines; the equity section lists its accounts and then
     * a current-period retained earnings line equal to net income. Assets
     * always equal liabilities plus equity because every journal entry in
     * scope balances. The ledger totals (equal debits and credits) are
     * included so the sheet can be checked against the trial balance. An
     * optional date range narrows the window and still reconciles.
     *
     * @param  array{date_from?: string|null, date_to?: string|null}  $parameters
     * @return array<string, mixed>
     */
    public function balanceSheet(array $parameters = []): array
    {
        $entries = $this->journalEntriesWithinRange($parameters);
        $balances = $this->accountBalancesWithinRange($parameters);

        $assets = $balances
            ->filter(fn (array $account) => $account['type'] === Account::TYPE_ASSET)
            ->map(fn (array $account) => [
                'account_id' => $account['id'],
                'code' => $account['code'],
                'name' => $account['name'],
                'balance' => $account['balance'],
                'amount' => round(-(float) $account['balance'], 2),
            ])
            ->sortBy('code')
            ->values()
            ->all();

        $liabilities = $balances
            ->filter(fn (array $account) => $account['type'] === Account::TYPE_LIABILITY)
            ->map(fn (array $account) => [
                'account_id' => $account['id'],
                'code' => $account['code'],
                'name' => $account['name'],
                'balance' => $account['balance'],
                'amount' => round((float) $account['balance'], 2),
            ])
            ->sortBy('code')
            ->values()
            ->all();

        $equity = $balances
            ->filter(fn (array $account) => $account['type'] === Account::TYPE_EQUITY)
            ->map(fn (array $account) => [
                'account_id' => $account['id'],
                'code' => $account['code'],
                'name' => $account['name'],
                'balance' => $account['balance'],
                'amount' => round((float) $account['balance'], 2),
            ])
            ->sortBy('code')
            ->values()
            ->all();

        // Retained earnings close net income for the period (the same figure
        // AccountingService::netIncome() derives from the live journal lines),
        // so the sheet balances: assets = liabilities + equity.
        $netIncome = round(
            (float) $balances
                ->filter(fn (array $account) => in_array($account['type'], [
                    Account::TYPE_REVENUE,
                    Account::TYPE_EXPENSE,
                ], true))
                ->sum('balance'),
            2,
        );

        $totalAssets = round((float) collect($assets)->sum('amount'), 2);
        $totalLiabilities = round((float) collect($liabilities)->sum('amount'), 2);
        $totalEquity = round(
            (float) collect($equity)->sum('amount') + $netIncome,
            2,
        );
        $totalLiabilitiesAndEquity = round($totalLiabilities + $totalEquity, 2);

        $lines = $entries->flatMap(fn (JournalEntry $entry) => $entry->lines);
        $ledger = [
            'total_debits' => round((float) $lines->sum('debit'), 2),
            'total_credits' => round((float) $lines->sum('credit'), 2),
        ];

        return [
            'type' => ReportSnapshot::TYPE_BALANCE_SHEET,
            'generated_at' => now()->toDateTimeString(),
            'summary' => [
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'total_equity' => $totalEquity,
                'net_income' => $netIncome,
                'balanced' => abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01,
            ],
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'retained_earnings' => $netIncome,
            'totals' => [
                'assets' => $totalAssets,
                'liabilities' => $totalLiabilities,
                'equity' => $totalEquity,
                'liabilities_and_equity' => $totalLiabilitiesAndEquity,
                'balanced' => abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01,
            ],
            'ledger' => $ledger,
        ];
    }

    /**
     * Purchasing report: spend by supplier, PO value by status and a
     * received-vs-pending summary built from the live purchase orders and
     * receipts.
     *
     * Spend is the actual received value (purchase receipt totals), grouped
     * by supplier; PO value by status sums order totals per status; and the
     * pending figure is the confirmed-but-not-yet-received PO value, matching
     * the dashboard's "Pending PO Value" KPI. An optional date range filters
     * orders by their ordered_at date and receipts by their received_at date.
     *
     * @param  array{date_from?: string|null, date_to?: string|null}  $parameters
     * @return array<string, mixed>
     */
    public function purchasing(array $parameters = []): array
    {
        $from = $parameters['date_from'] ?? null;
        $to = $parameters['date_to'] ?? null;

        /** @var Collection<int, PurchaseOrder> $orders */
        $orders = PurchaseOrder::query()
            ->with('supplier')
            ->when($from, fn ($query) => $query->whereDate('ordered_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('ordered_at', '<=', $to))
            ->orderBy('ordered_at')
            ->orderBy('id')
            ->get();

        /** @var Collection<int, PurchaseReceipt> $receipts */
        $receipts = PurchaseReceipt::query()
            ->with('purchaseOrder.supplier')
            ->when($from, fn ($query) => $query->whereDate('received_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('received_at', '<=', $to))
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        $ordersBySupplier = $orders
            ->groupBy('supplier_id')
            ->map(fn (Collection $supplierOrders) => [
                'order_count' => $supplierOrders->count(),
                'po_value' => round((float) $supplierOrders->sum('total'), 2),
            ]);

        $receiptsBySupplier = $receipts
            ->groupBy(fn (PurchaseReceipt $receipt) => $receipt->purchaseOrder?->supplier_id)
            ->map(fn (Collection $supplierReceipts) => [
                'receipt_count' => $supplierReceipts->count(),
                'received_value' => round((float) $supplierReceipts->sum('total'), 2),
            ]);

        $spendBySupplier = Supplier::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(function (Supplier $supplier) use ($ordersBySupplier, $receiptsBySupplier) {
                $orders = $ordersBySupplier->get($supplier->id) ?? [];
                $receipts = $receiptsBySupplier->get($supplier->id) ?? [];

                return [
                    'supplier_id' => (int) $supplier->id,
                    'supplier' => $supplier->name,
                    'order_count' => $orders['order_count'] ?? 0,
                    'po_value' => round($orders['po_value'] ?? 0, 2),
                    'receipt_count' => $receipts['receipt_count'] ?? 0,
                    'received_value' => round($receipts['received_value'] ?? 0, 2),
                ];
            })
            ->sortByDesc('received_value')
            ->values()
            ->all();

        $statuses = [
            PurchaseOrder::STATUS_RECEIVED => 'Received',
            PurchaseOrder::STATUS_CONFIRMED => 'Confirmed',
            PurchaseOrder::STATUS_DRAFT => 'Draft',
            PurchaseOrder::STATUS_CANCELLED => 'Cancelled',
        ];

        $poValueByStatus = collect($statuses)
            ->map(fn (string $label, string $status) => [
                'status' => $status,
                'status_label' => $label,
                'count' => $orders->where('status', $status)->count(),
                'value' => round((float) $orders->where('status', $status)->sum('total'), 2),
            ])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values()
            ->all();

        $totalSpend = round((float) $receipts->sum('total'), 2);
        $pendingValue = round(
            (float) $orders->where('status', PurchaseOrder::STATUS_CONFIRMED)->sum('total'),
            2,
        );

        return [
            'type' => ReportSnapshot::TYPE_PURCHASING,
            'generated_at' => now()->toDateTimeString(),
            'summary' => [
                'order_count' => $orders->count(),
                'total_spend' => $totalSpend,
                'received_value' => $totalSpend,
                'pending_value' => $pendingValue,
                'receipt_count' => $receipts->count(),
            ],
            'spend_by_supplier' => $spendBySupplier,
            'po_value_by_status' => $poValueByStatus,
            'received_vs_pending' => [
                'received' => $totalSpend,
                'pending' => $pendingValue,
            ],
        ];
    }

    /**
     * Journal entries dated inside the optional range, with their lines and
     * accounts loaded. Without a range every entry is included.
     *
     * @param  array{date_from?: string|null, date_to?: string|null}  $parameters
     * @return Collection<int, JournalEntry>
     */
    private function journalEntriesWithinRange(array $parameters): Collection
    {
        $from = $parameters['date_from'] ?? null;
        $to = $parameters['date_to'] ?? null;

        return JournalEntry::query()
            ->when($from, fn ($query) => $query->whereDate('journal_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('journal_date', '<=', $to))
            ->with(['lines.account'])
            ->orderBy('journal_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Account balances (credits minus debits) derived from the journal lines
     * dated inside the optional range, keyed by account id. Only accounts
     * with lines in scope appear.
     *
     * @param  array{date_from?: string|null, date_to?: string|null}  $parameters
     * @return Collection<int, array<string, mixed>>
     */
    private function accountBalancesWithinRange(array $parameters): Collection
    {
        return $this->journalEntriesWithinRange($parameters)
            ->flatMap(fn (JournalEntry $entry) => $entry->lines)
            ->groupBy('account_id')
            ->map(function (Collection $lines, $accountId) {
                $account = $lines->first()->account;

                return [
                    'id' => (int) $accountId,
                    'code' => $account?->code,
                    'name' => $account?->name ?? 'Account',
                    'type' => $account?->type ?? Account::TYPE_ASSET,
                    'balance' => round(
                        (float) $lines->sum('credit') - (float) $lines->sum('debit'),
                        2,
                    ),
                ];
            });
    }
}
