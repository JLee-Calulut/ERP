<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address_line1' => fake()->streetAddress(),
            'address_city' => fake()->city(),
            'address_state' => fake()->stateAbbr(),
            'address_postal_code' => fake()->postcode(),
            'address_country' => fake()->country(),
        ];
    }
}
