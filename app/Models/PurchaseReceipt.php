<?php

namespace App\Models;

use Database\Factories\PurchaseReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReceipt extends Model
{
    /** @use HasFactory<PurchaseReceiptFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'received_at',
        'total',
        'notes',
        'received_by',
        'journal_entry_id',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'total' => 'float',
            'posted_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Whether this receipt has already been posted to accounting.
     *
     * The one-time guard: a posted receipt carries a reference to its
     * journal entry and a posted_at timestamp, so the same receipt can never
     * capitalise inventory (or accrue a payable) twice.
     */
    public function isPosted(): bool
    {
        return $this->journal_entry_id !== null || $this->posted_at !== null;
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
