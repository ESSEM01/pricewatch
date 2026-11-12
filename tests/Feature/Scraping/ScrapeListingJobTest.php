<?php

use App\Enums\ListingStatus;
use App\Jobs\ScrapeListing;
use App\Models\Listing;
use App\Models\Store;
use App\Scraping\Http\UrlGuard;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12 10:00:00');
    $this->app->instance(UrlGuard::class, new UrlGuard(resolve: fn (string $host): array => ['93.184.216.34']));
});

function shopPage(string $price): string
{
    return '<html><head><script type="application/ld+json">'
        .json_encode(['@type' => 'Product', 'offers' => ['price' => $price, 'priceCurrency' => 'EUR']])
        .'</script></head><body></body></html>';
}

it('scrapes the page and records the price', function () {
    Http::fake([
        'shop.example/robots.txt' => Http::response(''),
        'shop.example/p/1' => Http::response(shopPage('89.90')),
    ]);
    $listing = Listing::factory()->create(['url' => 'https://shop.example/p/1', 'current_price' => null]);

    ScrapeListing::dispatchSync($listing);

    expect($listing->fresh())
        ->current_price->toBe(8990)
        ->last_checked_at->toDateTimeString()->toBe('2026-11-12 10:00:00')
        ->and($listing->pricePoints()->count())->toBe(1);
});

it('uses the store css selector when the page has no structured data', function () {
    Http::fake([
        'shop.example/robots.txt' => Http::response(''),
        'shop.example/p/1' => Http::response('<html><body><b id="p">12,50 €</b></body></html>'),
    ]);
    $store = Store::factory()->create(['price_selector' => '#p']);
    $listing = Listing::factory()->for($store)->create(['url' => 'https://shop.example/p/1']);

    ScrapeListing::dispatchSync($listing);

    expect($listing->fresh()->current_price)->toBe(1250);
});

it('records a failure when no price is found', function () {
    Http::fake([
        'shop.example/robots.txt' => Http::response(''),
        'shop.example/p/1' => Http::response('<html><body>Sold out</body></html>'),
    ]);
    $listing = Listing::factory()->create(['url' => 'https://shop.example/p/1', 'current_price' => 1000]);

    ScrapeListing::dispatchSync($listing);

    expect($listing->fresh())
        ->current_price->toBe(1000)
        ->consecutive_failures->toBe(1)
        ->last_error->toBe('No price found on the page.');
});

it('backs off at once when the product page is gone', function () {
    Http::fake([
        'shop.example/robots.txt' => Http::response(''),
        'shop.example/p/1' => Http::response('', 404),
    ]);
    $listing = Listing::factory()->create(['url' => 'https://shop.example/p/1']);

    ScrapeListing::dispatchSync($listing);

    expect($listing->fresh()->status)->toBe(ListingStatus::Failing);
});

it('skips paused listings without any request', function () {
    Http::fake();
    $listing = Listing::factory()->create(['status' => ListingStatus::Paused]);

    ScrapeListing::dispatchSync($listing);

    Http::assertNothingSent();
});

it('is dispatched for due listings by the scheduler command, with a lease', function () {
    Queue::fake();
    $due = Listing::factory()->create(['next_check_at' => null]);
    $notDue = Listing::factory()->create(['next_check_at' => now()->addHour()]);

    $this->artisan('scrape:due')->assertSuccessful();

    Queue::assertPushed(ScrapeListing::class, 1);
    Queue::assertPushed(fn (ScrapeListing $job) => $job->listing->is($due));
    // The lease stops the next scheduler tick from queuing it twice.
    expect($due->fresh()->next_check_at?->toDateTimeString())->toBe('2026-11-12 11:00:00');

    $this->artisan('scrape:due')->assertSuccessful();
    Queue::assertPushed(ScrapeListing::class, 1);
});

it('runs scrape:due every ten minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'scrape:due'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/10 * * * *');
});
