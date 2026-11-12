<?php

namespace App\Models;

use Database\Factories\PurchaseReceiptItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReceiptItem extends Model
{
    /** @use HasFactory<PurchaseReceiptItemFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_receipt_id',
        'purchase_order_item_id',
        'quantity_received',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'integer',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
