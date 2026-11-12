<?php

namespace Database\Factories;

use App\Models\Forecast;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Forecast>
 */
class ForecastFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $price = fake()->numberBetween(5_000, 100_000);

        return [
            'listing_id' => Listing::factory(),
            'model' => 'linear_trend',
            'horizon_days' => 14,
            'mape' => fake()->randomFloat(3, 0.5, 8),
            'points' => collect(range(1, 14))->map(fn (int $day) => [
                'date' => now()->addDays($day)->toDateString(),
                'price' => $price,
                'lower' => (int) round($price * 0.95),
                'upper' => (int) round($price * 1.05),
            ])->all(),
            'generated_at' => now(),
        ];
    }
}
