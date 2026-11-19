<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSnapshot extends Model
{
    public const TYPE_SALES = 'sales';

    public const TYPE_INVENTORY = 'inventory';

    public const TYPE_INCOME_STATEMENT = 'income_statement';

    public const TYPE_BALANCE_SHEET = 'balance_sheet';

    public const TYPE_PURCHASING = 'purchasing';

    public const TYPES = [
        self::TYPE_SALES,
        self::TYPE_INVENTORY,
        self::TYPE_INCOME_STATEMENT,
        self::TYPE_BALANCE_SHEET,
        self::TYPE_PURCHASING,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'type',
        'title',
        'parameters',
        'payload',
        'status',
        'error',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'payload' => 'array',
        ];
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Store the computed payload and move the snapshot to completed.
     *
     * Computed report output lives here (the snapshot/export table the README
     * anticipated) — never on the source tables. Any prior failure message is
     * cleared so a re-run leaves a clean record.
     */
    public function markCompleted(array $payload): void
    {
        $this->forceFill([
            'payload' => $payload,
            'status' => self::STATUS_COMPLETED,
            'error' => null,
        ])->save();
    }

    /**
     * Mark the snapshot as failed and keep the error message for review.
     */
    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'error' => $error,
        ])->save();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_SALES => 'Sales',
            self::TYPE_INVENTORY => 'Inventory',
            self::TYPE_INCOME_STATEMENT => 'Income Statement',
            self::TYPE_BALANCE_SHEET => 'Balance Sheet',
            self::TYPE_PURCHASING => 'Purchasing',
            default => ucfirst($this->type),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            default => ucfirst($this->status),
        };
    }

    /**
     * Download name for the snapshot's CSV export, named after the report
     * type (e.g. "income-statement-report.csv") so exports from different
     * reports are immediately distinguishable.
     */
    public function exportFilename(): string
    {
        return str_replace('_', '-', $this->type).'-report.csv';
    }
}
