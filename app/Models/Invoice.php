<?php

namespace App\Models;

use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PAID = 'paid';

    public const STATUSES = [
        self::STATUS_UNPAID,
        self::STATUS_PAID,
    ];

    protected $fillable = [
        'sales_order_id',
        'invoice_number',
        'issued_at',
        'total',
        'status',
        'journal_entry_id',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'total' => 'float',
            'posted_at' => 'datetime',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Whether this invoice has already been posted to accounting.
     *
     * A posted invoice carries a reference to its journal entry and a
     * posted_at timestamp; the one-time guard relies on this so the same
     * invoice can never credit revenue twice.
     */
    public function isPosted(): bool
    {
        return $this->journal_entry_id !== null || $this->posted_at !== null;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_UNPAID => 'Unpaid',
            self::STATUS_PAID => 'Paid',
            default => ucfirst($this->status),
        };
    }
}
