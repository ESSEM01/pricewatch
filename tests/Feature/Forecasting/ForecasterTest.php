<?php

use App\Events\PriceChanged;
use App\Forecasting\Exceptions\InsufficientHistory;
use App\Forecasting\Forecaster;
use App\Jobs\RefreshForecast;
use App\Models\Forecast;
use App\Models\Listing;
use App\Models\PricePoint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12 10:00:00');
});

/**
 * @param  list<int>  $prices  one per day, ending today
 */
function listingWithHistory(array $prices): Listing
{
    $listing = Listing::factory()->create();
    $start = now()->startOfDay()->subDays(count($prices) - 1)->setTime(9, 0);

    foreach ($prices as $i => $price) {
        PricePoint::factory()->for($listing)->create(['price' => $price, 'recorded_at' => $start->addDays($i)]);
    }

    return $listing;
}

it('forecasts 14 days with a confidence band and a backtest error', function () {
    $prices = array_map(fn (int $i) => 50000 - 50 * $i + (in_array($i % 7, [4, 5, 6], true) ? -3000 : 0), range(0, 59));

    $result = app(Forecaster::class)->forecast(listingWithHistory($prices));

    expect($result->points)->toHaveCount(14)
        ->and($result->points[0]['date'])->toBe('2026-11-13')
        ->and($result->points[13]['date'])->toBe('2026-11-26')
        ->and($result->mape)->toBeFloat()->toBeGreaterThanOrEqual(0);

    foreach ($result->points as $point) {
        expect($point['lower'])->toBeLessThanOrEqual($point['price'])
            ->and($point['upper'])->toBeGreaterThanOrEqual($point['price'])
            ->and($point['lower'])->toBeGreaterThanOrEqual(0);
    }
});

it('widens the band further into the future', function () {
    $prices = array_map(fn (int $i) => 10000 + (($i * 37) % 11 - 5) * 40, range(0, 59));

    $points = app(Forecaster::class)->forecast(listingWithHistory($prices))->points;

    expect($points[13]['upper'] - $points[13]['lower'])->toBeGreaterThan($points[0]['upper'] - $points[0]['lower']);
});

it('refuses to forecast with fewer than 14 days of history', function () {
    app(Forecaster::class)->forecast(listingWithHistory(array_fill(0, 13, 10000)));
})->throws(InsufficientHistory::class);

it('stores the forecast, replacing the previous one', function () {
    $listing = listingWithHistory(array_fill(0, 30, 10000));
    Forecast::factory()->for($listing)->create(['model' => 'old']);

    RefreshForecast::dispatchSync($listing);

    expect(Forecast::query()->where('listing_id', $listing->id)->sole())
        ->model->toBe('moving_average')
        ->horizon_days->toBe(14)
        ->points->toHaveCount(14);
});

it('drops a stale forecast when history is no longer sufficient', function () {
    $listing = listingWithHistory(array_fill(0, 5, 10000));
    Forecast::factory()->for($listing)->create();

    RefreshForecast::dispatchSync($listing);

    expect(Forecast::query()->count())->toBe(0);
});

it('refreshes the forecast when the price changes', function () {
    Queue::fake();
    $listing = Listing::factory()->create();

    event(new PriceChanged($listing, 10000, 9000));

    Queue::assertPushed(RefreshForecast::class, fn (RefreshForecast $job) => $job->listing->is($listing));
});

it('refreshes every forecast nightly', function () {
    Queue::fake();
    Listing::factory()->count(2)->create();

    $this->artisan('forecasts:refresh')->assertSuccessful();

    Queue::assertPushed(RefreshForecast::class, 2);
});
