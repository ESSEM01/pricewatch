<?php

namespace App\Forecasting\Contracts;

/**
 * A forecasting model over a daily price series.
 */
interface ForecastModel
{
    /** Stable identifier, stored with the forecast, e.g. "holt_winters". */
    public function name(): string;

    /** Fewest daily values the model needs to produce a forecast. */
    public function minimumPoints(): int;

    /**
     * @param  list<float>  $series  One value per day, oldest first.
     * @return list<float> One value per future day.
     */
    public function forecast(array $series, int $horizon): array;
}
