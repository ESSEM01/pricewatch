<?php

namespace App\Forecasting;

use App\Forecasting\Contracts\ForecastModel;

/**
 * Walk-forward backtest: pretend we are 4, 3, 2 and 1 weeks in the past,
 * forecast the following week with only the data known then, and compare
 * with what actually happened. Honest error figures, no peeking.
 */
final class Backtester
{
    public function __construct(
        private readonly int $folds = 4,
        private readonly int $foldDays = 7,
    ) {}

    /**
     * @param  list<float>  $series
     */
    public function run(ForecastModel $model, array $series): ?BacktestResult
    {
        $absoluteErrors = [];
        $percentageErrors = [];
        $residuals = [];

        for ($fold = $this->folds; $fold >= 1; $fold--) {
            $origin = count($series) - $fold * $this->foldDays;

            if ($origin < $model->minimumPoints()) {
                continue;
            }

            $predicted = $model->forecast(array_slice($series, 0, $origin), $this->foldDays);
            $actual = array_slice($series, $origin, $this->foldDays);

            foreach ($actual as $step => $value) {
                $residual = $value - $predicted[$step];
                $residuals[] = $residual;
                $absoluteErrors[] = abs($residual);

                if ($value != 0) {
                    $percentageErrors[] = abs($residual) / abs($value) * 100;
                }
            }
        }

        if ($residuals === []) {
            return null;
        }

        return new BacktestResult(
            mae: array_sum($absoluteErrors) / count($absoluteErrors),
            mape: $percentageErrors === [] ? 0.0 : array_sum($percentageErrors) / count($percentageErrors),
            residuals: $residuals,
        );
    }
}
