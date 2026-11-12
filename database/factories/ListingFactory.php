<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'store_id' => Store::factory(),
            'url' => fake()->url(),
            'is_own' => false,
            'status' => ListingStatus::Active,
            'consecutive_failures' => 0,
            'current_price' => fake()->numberBetween(1_000, 200_000),
            'currency' => 'EUR',
        ];
    }

    public function own(): static
    {
        return $this->state(fn (array $attributes) => ['is_own' => true]);
    }

    public function failing(int $failures = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingStatus::Failing,
            'consecutive_failures' => $failures,
        ]);
    }
}
