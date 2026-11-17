<?php

namespace App\Models;

use Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'entry_number',
        'journal_date',
        'description',
        'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'journal_date' => 'date',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * The sales invoices referencing this entry — present when the entry was
     * created by posting an invoice to accounting.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'journal_entry_id');
    }

    /**
     * The purchase receipts referencing this entry — present when the entry
     * was created by posting a receipt to accounting.
     */
    public function purchaseReceipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class, 'journal_entry_id');
    }

    /**
     * Total value of the entry — the summed debits. For a balanced entry this
     * is identical to the summed credits.
     */
    public function total(): float
    {
        return (float) $this->lines()->sum('debit');
    }
}
