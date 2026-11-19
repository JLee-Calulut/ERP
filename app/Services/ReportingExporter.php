<?php

namespace App\Services;

use App\Models\ReportSnapshot;
use InvalidArgumentException;

/**
 * Serializes a completed report snapshot into flat CSV rows: one header row
 * plus one row per record. Each report type maps to the primary table shown
 * on its report page, with stable, human-readable column names, so the CSV is
 * a faithful, row-oriented view of the computed payload.
 */
class ReportingExporter
{
    /**
     * Build the CSV table for a snapshot.
     *
     * @return array{columns: string[], rows: array<int, array<int, int|float|string|null>>}
     */
    public function export(ReportSnapshot $snapshot): array
    {
        $payload = $snapshot->payload ?: [];

        return match ($snapshot->type) {
            ReportSnapshot::TYPE_SALES => [
                'columns' => ['Month', 'Revenue'],
                'rows' => $this->rows($payload['revenue_by_month'] ?? [], ['label', 'revenue']),
            ],
            ReportSnapshot::TYPE_INVENTORY => [
                'columns' => ['Category', 'Products', 'Units in stock', 'Valuation'],
                'rows' => $this->rows(
                    $payload['valuation_by_category'] ?? [],
                    ['category', 'product_count', 'units', 'valuation'],
                ),
            ],
            ReportSnapshot::TYPE_INCOME_STATEMENT => $this->statementRows([
                'Revenue' => $payload['revenue'] ?? [],
                'Expenses' => $payload['expenses'] ?? [],
            ]),
            ReportSnapshot::TYPE_BALANCE_SHEET => $this->statementRows([
                'Assets' => $payload['assets'] ?? [],
                'Liabilities' => $payload['liabilities'] ?? [],
                'Equity' => $payload['equity'] ?? [],
            ]),
            ReportSnapshot::TYPE_PURCHASING => [
                'columns' => ['Supplier', 'Orders', 'PO value', 'Received value'],
                'rows' => $this->rows(
                    $payload['spend_by_supplier'] ?? [],
                    ['supplier', 'order_count', 'po_value', 'received_value'],
                ),
            ],
            default => throw new InvalidArgumentException(
                "Cannot export reports of type [{$snapshot->type}]."
            ),
        };
    }

    /**
     * Flatten each payload record to the selected scalar fields in order.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @param  list<string>  $fields
     * @return array<int, array<int, int|float|string|null>>
     */
    private function rows(array $records, array $fields): array
    {
        return array_values(array_map(
            fn (array $record): array => array_map(
                fn (string $field) => $record[$field] ?? null,
                $fields,
            ),
            $records,
        ));
    }

    /**
     * Grouped rows for the statements' account sections. Each account row
     * carries its section, chart code, name and statement amount.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $sections
     * @return array{columns: string[], rows: array<int, array<int, int|float|string|null>>}
     */
    private function statementRows(array $sections): array
    {
        $rows = [];

        foreach ($sections as $section => $accounts) {
            foreach ($accounts as $account) {
                $rows[] = [
                    $section,
                    $account['code'] ?? null,
                    $account['name'] ?? null,
                    $account['amount'] ?? 0,
                ];
            }
        }

        return [
            'columns' => ['Section', 'Code', 'Account', 'Amount'],
            'rows' => $rows,
        ];
    }
}
