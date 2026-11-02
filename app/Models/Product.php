<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'description',
        'cost_price',
        'selling_price',
        'stock_quantity',
        'low_stock_threshold',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'float',
            'selling_price' => 'float',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class);
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function stockStatus(): string
    {
        if ($this->stock_quantity === 0) {
            return 'out';
        }

        return $this->isLowStock() ? 'low' : 'in';
    }
}
