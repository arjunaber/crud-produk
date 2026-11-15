<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    // database/factories/ProductFactory.php
public function definition(): array
{
    return [
        'name'  => ucwords(fake()->words(3, true)),
        'price' => fake()->numberBetween(15, 500) * 1000,
        'stock' => fake()->numberBetween(5, 100),
    ];
}

}