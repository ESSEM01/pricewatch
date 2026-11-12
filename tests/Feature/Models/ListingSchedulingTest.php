<?php

use App\Enums\ListingStatus;
use App\Events\PriceChanged;
use App\Models\Listing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12 10:00:00');
});

it('schedules the next check and clears the last error after a success', function () {
    $listing = Listing::factory()->create(['last_error' => 'boom']);

    $listing->recordPrice(5000, 'EUR');

    expect($listing->fresh())
        ->next_check_at->toDateTimeString()->toBe('2026-11-12 16:00:00')
        ->last_error->toBeNull();
});

it('retries soon after the first failures, with growing delays', function () {
    $listing = Listing::factory()->create();

    $listing->recordFailure('timeout');
    expect($listing->fresh())
        ->status->toBe(ListingStatus::Active)
        ->consecutive_failures->toBe(1)
        ->last_error->toBe('timeout')
        ->next_check_at->toDateTimeString()->toBe('2026-11-12 10:15:00');

    $listing->recordFailure('timeout');
    expect($listing->fresh()->next_check_at?->toDateTimeString())->toBe('2026-11-12 10:30:00');
});

it('marks the listing failing after 3 consecutive errors and waits 6 hours', function () {
    $listing = Listing::factory()->create();

    foreach (range(1, 3) as $attempt) {
        $listing->recordFailure('HTTP 500');
    }

    expect($listing->fresh())
        ->status->toBe(ListingStatus::Failing)
        ->consecutive_failures->toBe(3)
        ->next_check_at->toDateTimeString()->toBe('2026-11-12 16:00:00');
});

it('backs off immediately on a permanent failure', function () {
    $listing = Listing::factory()->create();

    $listing->recordFailure('HTTP 404', permanent: true);

    expect($listing->fresh())
        ->status->toBe(ListingStatus::Failing)
        ->next_check_at->toDateTimeString()->toBe('2026-11-12 16:00:00');
});

it('keeps a paused listing paused when a failure is recorded', function () {
    $listing = Listing::factory()->create(['status' => ListingStatus::Paused]);

    $listing->recordFailure('HTTP 500', permanent: true);

    expect($listing->fresh()->status)->toBe(ListingStatus::Paused);
});

it('truncates very long error messages', function () {
    $listing = Listing::factory()->create();

    $listing->recordFailure(str_repeat('x', 2000));

    expect(mb_strlen((string) $listing->fresh()->last_error))->toBeLessThanOrEqual(500);
});

it('announces a price change, but not the first price or an unchanged one', function () {
    Event::fake([PriceChanged::class]);
    $listing = Listing::factory()->create(['current_price' => null]);

    $listing->recordPrice(10000, 'EUR');
    Carbon::setTestNow('2026-11-13 10:00:00');
    $listing->recordPrice(10000, 'EUR'); // heartbeat only
    $listing->recordPrice(8990, 'EUR');

    Event::assertDispatchedTimes(PriceChanged::class, 1);
    Event::assertDispatched(fn (PriceChanged $event) => $event->listing->is($listing)
        && $event->previousPrice === 10000
        && $event->newPrice === 8990);
});

it('finds listings that are due and not paused', function () {
    $due = Listing::factory()->create(['next_check_at' => null]);
    $overdue = Listing::factory()->create(['next_check_at' => now()->subMinute()]);
    $failingDue = Listing::factory()->failing()->create(['next_check_at' => now()->subMinute()]);
    Listing::factory()->create(['next_check_at' => now()->addMinute()]);
    Listing::factory()->create(['status' => ListingStatus::Paused, 'next_check_at' => null]);

    expect(Listing::query()->due()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$due->id, $overdue->id, $failingDue->id])->sort()->values()->all());
});
