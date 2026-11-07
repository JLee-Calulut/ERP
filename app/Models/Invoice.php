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
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'total' => 'float',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
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
