<?php

namespace Database\Factories;

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertRule>
 */
class AlertRuleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            // The rule belongs to whoever owns the product.
            'user_id' => fn (array $attributes) => Product::query()->whereKey($attributes['product_id'])->value('user_id'),
            'type' => AlertRuleType::BelowThreshold,
            'threshold' => fake()->numberBetween(1_000, 100_000),
            'is_active' => true,
        ];
    }

    public function competitorUndercut(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AlertRuleType::CompetitorUndercut,
            'threshold' => null,
        ]);
    }

    public function changePercent(int $basisPoints = 500): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AlertRuleType::ChangePercent,
            'threshold' => $basisPoints,
        ]);
    }
}
