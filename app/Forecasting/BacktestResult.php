<?php

namespace App\Forecasting;

final readonly class BacktestResult
{
    /**
     * @param  float  $mae  Mean absolute error, in the series' unit (cents).
     * @param  float  $mape  Mean absolute percentage error, 0-100.
     * @param  list<float>  $residuals  actual - predicted, for every backtested day.
     */
    public function __construct(
        public float $mae,
        public float $mape,
        public array $residuals,
    ) {}

    /** Root mean square of the residuals: the spread used for confidence bands. */
    public function rmse(): float
    {
        if ($this->residuals === []) {
            return 0.0;
        }

        $squares = array_map(fn (float $r) => $r ** 2, $this->residuals);

        return sqrt(array_sum($squares) / count($squares));
    }
}
