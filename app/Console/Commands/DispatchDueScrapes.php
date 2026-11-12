<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeListing;
use App\Models\Listing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scrape:due {--limit=500 : Maximum number of listings to queue}')]
#[Description('Queue a scrape for every listing whose next check is due')]
class DispatchDueScrapes extends Command
{
    /** How long a queued listing is held before it can be queued again. */
    private const LEASE_MINUTES = 60;

    public function handle(): int
    {
        $listings = Listing::query()
            ->due()
            ->orderByRaw('next_check_at IS NOT NULL, next_check_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($listings as $listing) {
            // Lease: push next_check_at forward so the next tick does not queue it
            // again while it waits; the job sets the real next check when it runs.
            $listing->forceFill(['next_check_at' => now()->addMinutes(self::LEASE_MINUTES)])->save();

            ScrapeListing::dispatch($listing);
        }

        $this->components->info("Queued {$listings->count()} listing(s).");

        return self::SUCCESS;
    }
}
