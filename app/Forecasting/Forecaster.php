<?php

namespace App\Forecasting;

use App\Forecasting\Exceptions\InsufficientHistory;
use App\Models\Listing;
use App\Models\PricePoint;

/**
 * Turns a listing's price history into a 14-day forecast with a confidence
 * band, using whichever model backtests best on that listing.
 */
final class Forecaster
{
    public const MINIMUM_DAYS = 14;

    public const HORIZON_DAYS = 14;

    /** Older history adds little and slows the backtest down. */
    private const LOOKBACK_DAYS = 120;

    /** 95% two-sided normal quantile. */
    private const Z = 1.96;

    /** The backtest measures errors over days 1-7 ahead, on average day 4. */
    private const BACKTEST_MEAN_STEP = 4;

    public function __construct(private readonly ModelSelector $selector) {}

    /**
     * @throws InsufficientHistory
     */
    public function forecast(Listing $listing): ForecastResult
    {
        $series = $this->history($listing);

        $selection = $series->count() >= self::MINIMUM_DAYS ? $this->selector->select($series->values()) : null;

        if ($selection === null) {
            throw InsufficientHistory::days($series->count(), self::MINIMUM_DAYS);
        }

        $predictions = $selection->model->forecast($series->values(), self::HORIZON_DAYS);
        $sigma = $selection->backtest->rmse();
        $points = [];

        foreach ($predictions as $index => $predicted) {
            $step = $index + 1;
            // Uncertainty grows with the distance from today (random-walk style).
            $margin = self::Z * $sigma * sqrt($step / self::BACKTEST_MEAN_STEP);
            $price = max(0, (int) round($predicted));

            $points[] = [
                'date' => $series->end()->addDays($step)->toDateString(),
                'price' => $price,
                'lower' => max(0, (int) round($predicted - $margin)),
                'upper' => max($price, (int) round($predicted + $margin)),
            ];
        }

        return new ForecastResult($selection->model->name(), round($selection->backtest->mape, 3), $points);
    }

    public function history(Listing $listing): DailySeries
    {
        $points = $listing->pricePoints()
            ->where('recorded_at', '>=', now()->startOfDay()->subDays(self::LOOKBACK_DAYS))
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get(['price', 'recorded_at'])
            ->map(fn (PricePoint $point): array => [$point->recorded_at, $point->price]);

        return DailySeries::fromPoints($points, until: now());
    }
}
