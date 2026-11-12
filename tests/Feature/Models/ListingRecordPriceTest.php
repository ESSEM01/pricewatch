<?php

use App\Enums\ListingStatus;
use App\Models\Listing;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12 10:00:00');
});

it('stores the first price it sees', function () {
    $listing = Listing::factory()->create(['current_price' => null]);

    $point = $listing->recordPrice(129900, 'EUR');

    expect($point)->not->toBeNull()
        ->and($point->price)->toBe(129900)
        ->and($listing->fresh()->current_price)->toBe(129900)
        ->and($listing->fresh()->last_checked_at?->toDateTimeString())->toBe('2026-11-12 10:00:00');
});

it('does not store an unchanged price checked again the same day', function () {
    $listing = Listing::factory()->create(['current_price' => null]);
    $listing->recordPrice(129900, 'EUR');

    Carbon::setTestNow('2026-11-12 16:00:00');
    $point = $listing->recordPrice(129900, 'EUR');

    expect($point)->toBeNull()
        ->and($listing->pricePoints()->count())->toBe(1)
        ->and($listing->fresh()->last_checked_at?->toDateTimeString())->toBe('2026-11-12 16:00:00');
});

it('stores a price change immediately', function () {
    $listing = Listing::factory()->create(['current_price' => null]);
    $listing->recordPrice(129900, 'EUR');

    Carbon::setTestNow('2026-11-12 11:00:00');
    $point = $listing->recordPrice(119900, 'EUR');

    expect($point?->price)->toBe(119900)
        ->and($listing->pricePoints()->count())->toBe(2)
        ->and($listing->fresh()->current_price)->toBe(119900);
});

it('stores a daily heartbeat for an unchanged price after 24 hours', function () {
    $listing = Listing::factory()->create(['current_price' => null]);
    $listing->recordPrice(129900, 'EUR');

    Carbon::setTestNow('2026-11-13 10:00:00');

    expect($listing->recordPrice(129900, 'EUR'))->not->toBeNull()
        ->and($listing->pricePoints()->count())->toBe(2);
});

it('treats a currency change as a price change', function () {
    $listing = Listing::factory()->create(['current_price' => null]);
    $listing->recordPrice(129900, 'EUR');

    expect($listing->recordPrice(129900, 'USD'))->not->toBeNull()
        ->and($listing->fresh()->currency)->toBe('USD');
});

it('resets the failure counter and reactivates a failing listing on success', function () {
    $listing = Listing::factory()->create([
        'status' => ListingStatus::Failing,
        'consecutive_failures' => 3,
    ]);

    $listing->recordPrice(5000, 'EUR');

    expect($listing->fresh())
        ->status->toBe(ListingStatus::Active)
        ->consecutive_failures->toBe(0);
});

it('rejects negative prices', function () {
    Listing::factory()->create()->recordPrice(-1, 'EUR');
})->throws(InvalidArgumentException::class);
