<?php

namespace App\Forecasting;

use App\Forecasting\Contracts\ForecastModel;

/**
 * Backtests every candidate and keeps the one with the lowest MAE.
 * Candidates are ordered simplest first, and ties keep the simpler one.
 */
final class ModelSelector
{
    /**
     * @param  list<ForecastModel>  $models  Simplest first.
     */
    public function __construct(
        private readonly array $models,
        private readonly Backtester $backtester,
    ) {}

    /**
     * @param  list<float>  $series
     */
    public function select(array $series): ?ModelSelection
    {
        $best = null;

        foreach ($this->models as $model) {
            $result = $this->backtester->run($model, $series);

            if ($result !== null && ($best === null || $result->mae < $best->backtest->mae - 1e-9)) {
                $best = new ModelSelection($model, $result);
            }
        }

        return $best;
    }
}
