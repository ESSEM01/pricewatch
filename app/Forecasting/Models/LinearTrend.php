<?php

namespace App\Forecasting\Models;

use App\Forecasting\Contracts\ForecastModel;

/**
 * Least-squares line over the recent window, extrapolated forward.
 * Captures steady drifts such as a product slowly getting cheaper.
 */
final class LinearTrend implements ForecastModel
{
    public function __construct(private readonly int $window = 42) {}

    public function name(): string
    {
        return 'linear_trend';
    }

    public function minimumPoints(): int
    {
        return 7;
    }

    public function forecast(array $series, int $horizon): array
    {
        $y = array_slice($series, -$this->window);
        $n = count($y);
        $meanX = ($n - 1) / 2;
        $meanY = array_sum($y) / $n;

        $covariance = 0.0;
        $variance = 0.0;
        foreach ($y as $x => $value) {
            $covariance += ($x - $meanX) * ($value - $meanY);
            $variance += ($x - $meanX) ** 2;
        }

        $slope = $variance > 0 ? $covariance / $variance : 0.0;
        $intercept = $meanY - $slope * $meanX;

        $forecast = [];
        for ($step = 0; $step < $horizon; $step++) {
            $forecast[] = $intercept + $slope * ($n + $step);
        }

        return $forecast;
    }
}
