<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Record a stock movement and update the product's stock level atomically.
     *
     * - in:         quantity is added to the current stock.
     * - out:        quantity is deducted and may not exceed current stock.
     * - adjustment: quantity is the new absolute stock level (>= 0).
     *
     * @throws ValidationException when an "out" movement would drive stock below zero.
     */
    public function adjust(
        Product $product,
        string $type,
        int $quantity,
        ?string $reason = null,
        ?User $user = null,
    ): StockTransaction {
        return DB::transaction(function () use ($product, $type, $quantity, $reason, $user) {
            $current = (int) $product->stock_quantity;

            $delta = match ($type) {
                StockTransaction::TYPE_IN => $quantity,
                StockTransaction::TYPE_OUT => -$quantity,
                StockTransaction::TYPE_ADJUSTMENT => $quantity - $current,
                default => throw ValidationException::withMessages([
                    'type' => 'The selected type is invalid.',
                ]),
            };

            $newQuantity = $current + $delta;

            if ($newQuantity < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'The quantity cannot exceed the current stock level.',
                ]);
            }

            $product->update(['stock_quantity' => $newQuantity]);

            return StockTransaction::create([
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $delta,
                'previous_quantity' => $current,
                'new_quantity' => $newQuantity,
                'reason' => $reason,
                'user_id' => $user?->id,
            ]);
        });
    }
}
