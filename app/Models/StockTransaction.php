<?php

namespace App\Models;

use Database\Factories\StockTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransaction extends Model
{
    /** @use HasFactory<StockTransactionFactory> */
    use HasFactory;

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPES = [
        self::TYPE_IN,
        self::TYPE_OUT,
        self::TYPE_ADJUSTMENT,
    ];

    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'previous_quantity',
        'new_quantity',
        'reason',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'previous_quantity' => 'integer',
            'new_quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_IN => 'Received',
            self::TYPE_OUT => 'Issued',
            self::TYPE_ADJUSTMENT => 'Adjusted',
            default => ucfirst($this->type),
        };
    }
}
