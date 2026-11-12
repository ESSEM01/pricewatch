<?php

namespace App\Console\Commands;

use App\Enums\ListingStatus;
use App\Jobs\RefreshForecast;
use App\Models\Listing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('forecasts:refresh')]
#[Description('Queue a forecast recomputation for every listing that is not paused')]
class RefreshForecasts extends Command
{
    public function handle(): int
    {
        $count = 0;

        Listing::query()
            ->where('status', '!=', ListingStatus::Paused)
            ->lazyById()
            ->each(function (Listing $listing) use (&$count): void {
                RefreshForecast::dispatch($listing);
                $count++;
            });

        $this->components->info("Queued {$count} forecast(s).");

        return self::SUCCESS;
    }
}
