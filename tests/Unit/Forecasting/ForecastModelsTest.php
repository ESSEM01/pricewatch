<?php

use App\Forecasting\Backtester;
use App\Forecasting\Models\HoltWinters;
use App\Forecasting\Models\LinearTrend;
use App\Forecasting\Models\MovingAverage;
use App\Forecasting\ModelSelector;

/** y = 100 - 0.5t */
function linearSeries(int $length): array
{
    return array_map(fn (int $t) => 100 - 0.5 * $t, range(0, $length - 1));
}

/** The same week repeated: cheaper Friday to Sunday. */
function weeklySeries(int $weeks, float $trendPerDay = 0.0): array
{
    $week = [100, 100, 100, 100, 88, 88, 88];
    $series = [];

    for ($t = 0; $t < $weeks * 7; $t++) {
        $series[] = $week[$t % 7] + $trendPerDay * $t;
    }

    return $series;
}

it('extrapolates a straight line exactly', function () {
    $forecast = (new LinearTrend)->forecast(linearSeries(30), 7);

    foreach ($forecast as $step => $value) {
        expect(abs($value - (100 - 0.5 * (30 + $step))))->toBeLessThan(0.01);
    }
});

it('projects the mean of the last week as a flat line', function () {
    $series = [...array_fill(0, 10, 50.0), 10, 20, 30, 40, 50, 60, 70];

    expect((new MovingAverage)->forecast($series, 3))->toBe([40.0, 40.0, 40.0]);
});

it('repeats a weekly pattern', function () {
    $forecast = (new HoltWinters)->forecast(weeklySeries(6), 7);

    foreach ($forecast as $step => $value) {
        expect(abs($value - [100, 100, 100, 100, 88, 88, 88][$step]))->toBeLessThan(0.5);
    }
});

it('declares how much history each model needs', function () {
    expect((new MovingAverage)->minimumPoints())->toBe(7)
        ->and((new LinearTrend)->minimumPoints())->toBe(7)
        ->and((new HoltWinters)->minimumPoints())->toBe(14);
});

it('backtests walk-forward and reports MAE, MAPE and residuals', function () {
    $result = (new Backtester)->run(new LinearTrend, linearSeries(60));

    expect($result)->not->toBeNull()
        ->and($result->mae)->toBeLessThan(0.0001)
        ->and($result->mape)->toBeLessThan(0.0001)
        ->and($result->residuals)->toHaveCount(28); // 4 folds x 7 days
});

it('cannot backtest a model on too little history', function () {
    expect((new Backtester)->run(new HoltWinters, weeklySeries(2)))->toBeNull();
});

it('picks the model with the lowest backtest error', function (array $series, string $expected) {
    $selector = new ModelSelector([new MovingAverage, new LinearTrend, new HoltWinters], new Backtester);

    expect($selector->select($series)?->model->name())->toBe($expected);
})->with([
    'weekly promos' => [weeklySeries(8), 'holt_winters'],
    'weekly promos on a slow decline' => [weeklySeries(8, -0.05), 'holt_winters'],
    'steady decline' => [linearSeries(60), 'linear_trend'],
    'flat: ties go to the simplest model' => [array_fill(0, 60, 42.0), 'moving_average'],
]);

it('selects nothing when no model can be evaluated', function () {
    $selector = new ModelSelector([new MovingAverage, new LinearTrend, new HoltWinters], new Backtester);

    expect($selector->select([1.0, 2.0, 3.0]))->toBeNull();
});
