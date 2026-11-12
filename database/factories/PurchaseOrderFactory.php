<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'status' => PurchaseOrder::STATUS_DRAFT,
            'ordered_at' => now(),
            'subtotal' => 0,
            'total' => 0,
            'notes' => null,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the purchase order is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrder::STATUS_CONFIRMED,
        ]);
    }

    /**
     * Indicate that the purchase order has been received.
     */
    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrder::STATUS_RECEIVED,
        ]);
    }
}
