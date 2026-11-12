<?php

namespace App\Forecasting\Models;

use App\Forecasting\Contracts\ForecastModel;

/**
 * Baseline: tomorrow looks like the average of the last week.
 * Hard to beat on stable prices, and the yardstick for the others.
 */
final class MovingAverage implements ForecastModel
{
    public function __construct(private readonly int $window = 7) {}

    public function name(): string
    {
        return 'moving_average';
    }

    public function minimumPoints(): int
    {
        return $this->window;
    }

    public function forecast(array $series, int $horizon): array
    {
        $recent = array_slice($series, -$this->window);
        // fdiv keeps the result a float even when the division is exact.
        $mean = fdiv(array_sum($recent), count($recent));

        return array_fill(0, $horizon, $mean);
    }
}
