<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    private const CATEGORIES = ['Smartphone', 'Cuffie wireless', 'Smart TV', 'Aspirapolvere', 'Console', 'E-reader'];

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => self::CATEGORIES[fake()->numberBetween(0, count(self::CATEGORIES) - 1)].' '.fake()->bothify('??-###'),
            'sku' => fake()->optional()->bothify('SKU-####-??'),
        ];
    }
}
