<?php

namespace App\Forecasting;

use App\Forecasting\Contracts\ForecastModel;

final readonly class ModelSelection
{
    public function __construct(
        public ForecastModel $model,
        public BacktestResult $backtest,
    ) {}
}
