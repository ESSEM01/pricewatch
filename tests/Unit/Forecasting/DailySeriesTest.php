<?php

use App\Forecasting\DailySeries;
use App\Forecasting\Signals\WeeklyPromoDetector;
use Carbon\CarbonImmutable;

function day(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date);
}

it('turns sparse price changes into one value per day', function () {
    $series = DailySeries::fromPoints([
        [day('2026-11-01 09:00'), 10000],
        [day('2026-11-04 08:00'), 9000],
        [day('2026-11-04 20:00'), 9500], // last price of the day wins
    ], until: day('2026-11-06 12:00'));

    expect($series->values())->toBe([10000.0, 10000.0, 10000.0, 9500.0, 9500.0, 9500.0])
        ->and($series->start()->toDateString())->toBe('2026-11-01')
        ->and($series->end()->toDateString())->toBe('2026-11-06');
});

it('is empty without points', function () {
    expect(DailySeries::fromPoints([], until: day('2026-11-06'))->values())->toBe([]);
});

it('spots weekdays with recurring promotions', function () {
    // 8 weeks starting on a Monday; every other weekend 12% off.
    $points = [];
    $start = day('2026-09-14');
    for ($i = 0; $i < 56; $i++) {
        $date = $start->addDays($i);
        $promo = $date->isoWeekday() >= 5 && intdiv($i, 7) % 2 === 0;
        $points[] = [$date, $promo ? 8800 : 10000];
    }

    $series = DailySeries::fromPoints($points, until: $start->addDays(55));

    expect((new WeeklyPromoDetector)->detect($series))->toBe([5, 6, 7]);
});

it('does not mistake a steady decline or small noise for a weekly promo', function (array $prices) {
    $start = day('2026-09-14');
    $points = array_map(fn (int $i) => [$start->addDays($i), $prices[$i]], array_keys($prices));

    expect((new WeeklyPromoDetector)->detect(DailySeries::fromPoints($points, until: $start->addDays(count($prices) - 1))))->toBe([]);
})->with([
    'steady decline' => [array_map(fn (int $i) => 10000 - 40 * $i, range(0, 55))],
    'noise within 4%' => [array_map(fn (int $i) => 10000 + (($i * 37) % 9 - 4) * 100, range(0, 55))],
]);
