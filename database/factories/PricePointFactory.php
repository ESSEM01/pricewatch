<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\PricePoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricePoint>
 */
class PricePointFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'price' => fake()->numberBetween(1_000, 200_000),
            'currency' => 'EUR',
            'recorded_at' => fake()->dateTimeBetween('-90 days'),
        ];
    }
}
