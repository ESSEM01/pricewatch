<?php

namespace App\Listeners;

use App\Events\PriceChanged;
use App\Jobs\RefreshForecast;

final class RefreshForecastOnPriceChange
{
    public function handle(PriceChanged $event): void
    {
        RefreshForecast::dispatch($event->listing);
    }
}
