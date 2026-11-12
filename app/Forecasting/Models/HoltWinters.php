<?php

namespace App\Forecasting\Models;

use App\Forecasting\Contracts\ForecastModel;

/**
 * Additive Holt-Winters (triple exponential smoothing) with a weekly
 * season: level + trend + day-of-week effect. Captures weekend promos.
 */
final class HoltWinters implements ForecastModel
{
    public function __construct(
        private readonly int $season = 7,
        private readonly float $alpha = 0.4,  // level smoothing
        private readonly float $beta = 0.05,  // trend smoothing
        private readonly float $gamma = 0.3,  // seasonal smoothing
    ) {}

    public function name(): string
    {
        return 'holt_winters';
    }

    public function minimumPoints(): int
    {
        return 2 * $this->season; // two full seasons to initialise
    }

    public function forecast(array $series, int $horizon): array
    {
        $m = $this->season;
        $n = count($series);

        // Initialise from the first two seasons.
        $firstMean = array_sum(array_slice($series, 0, $m)) / $m;
        $secondMean = array_sum(array_slice($series, $m, $m)) / $m;
        $level = $firstMean;
        $trend = ($secondMean - $firstMean) / $m;
        $seasonal = array_map(fn (float $value) => $value - $firstMean, array_slice($series, 0, $m));

        for ($t = $m; $t < $n; $t++) {
            $s = $seasonal[$t % $m];
            $previousLevel = $level;

            $level = $this->alpha * ($series[$t] - $s) + (1 - $this->alpha) * ($level + $trend);
            $trend = $this->beta * ($level - $previousLevel) + (1 - $this->beta) * $trend;
            $seasonal[$t % $m] = $this->gamma * ($series[$t] - $level) + (1 - $this->gamma) * $s;
        }

        $forecast = [];
        for ($h = 1; $h <= $horizon; $h++) {
            $forecast[] = $level + $h * $trend + $seasonal[($n + $h - 1) % $m];
        }

        return $forecast;
    }
}
