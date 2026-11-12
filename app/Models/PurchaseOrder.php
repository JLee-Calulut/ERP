<?php

namespace App\Models;

use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CONFIRMED,
        self::STATUS_RECEIVED,
        self::STATUS_CANCELLED,
    ];

    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_RECEIVED, self::STATUS_CANCELLED],
        self::STATUS_RECEIVED => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'supplier_id',
        'status',
        'ordered_at',
        'subtotal',
        'total',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'float',
            'total' => 'float',
            'ordered_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->with('product');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_RECEIVED => 'Received',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($this->status),
        };
    }
}
