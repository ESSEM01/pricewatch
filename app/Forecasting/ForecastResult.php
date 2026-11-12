<?php

namespace App\Forecasting;

final readonly class ForecastResult
{
    /**
     * @param  list<array{date: string, price: int, lower: int, upper: int}>  $points  Cents.
     */
    public function __construct(
        public string $model,
        public float $mape,
        public array $points,
    ) {}
}
