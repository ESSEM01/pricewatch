<?php

namespace App\Jobs;

use App\Forecasting\Exceptions\InsufficientHistory;
use App\Forecasting\Forecaster;
use App\Models\Listing;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recomputes and stores one listing's forecast, so pages only read it.
 */
class RefreshForecast implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public Listing $listing) {}

    /** Several price changes in a row queue a single recomputation. */
    public function uniqueId(): string
    {
        return (string) $this->listing->id;
    }

    public function handle(Forecaster $forecaster): void
    {
        try {
            $result = $forecaster->forecast($this->listing);
        } catch (InsufficientHistory) {
            $this->listing->forecast()->delete();

            return;
        }

        $this->listing->forecast()->updateOrCreate([], [
            'model' => $result->model,
            'horizon_days' => Forecaster::HORIZON_DAYS,
            'mape' => $result->mape,
            'points' => $result->points,
            'generated_at' => now(),
        ]);
    }
}
