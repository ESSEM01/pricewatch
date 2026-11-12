<?php

namespace App\Jobs;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Scraping\Exceptions\ScrapeFailed;
use App\Scraping\PriceScraper;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Scrapes one listing. Expected failures are recorded on the listing (and
 * drive its retry schedule); only unexpected exceptions fail the job.
 */
class ScrapeListing implements ShouldQueue
{
    use Queueable;

    /** One unexpected exception is enough; rate-limit releases do not count. */
    public int $maxExceptions = 1;

    public int $timeout = 60;

    public function __construct(public Listing $listing) {}

    /**
     * Rate-limited jobs are released back to the queue, so allow retries for
     * a while instead of a fixed number of attempts.
     */
    public function retryUntil(): CarbonInterface
    {
        return now()->addHour();
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping((string) $this->listing->id))->dontRelease()->expireAfter(120),
            new RateLimited('scraping'),
        ];
    }

    public function handle(PriceScraper $scraper): void
    {
        if ($this->listing->status === ListingStatus::Paused) {
            return;
        }

        try {
            $price = $scraper->scrape($this->listing);
        } catch (ScrapeFailed $e) {
            $this->listing->recordFailure($e->getMessage(), $e->permanent);

            return;
        }

        $this->listing->recordPrice($price->amount, $price->currency);
    }
}
