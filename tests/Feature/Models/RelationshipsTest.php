<?php

use App\Models\AlertRule;
use App\Models\Forecast;
use App\Models\Listing;
use App\Models\PricePoint;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

it('links a user to products, listings and stores', function () {
    $user = User::factory()->create();
    $product = Product::factory()->for($user)->create();
    $store = Store::factory()->create();
    $listing = Listing::factory()->for($product)->for($store)->own()->create();

    expect($user->products->pluck('id')->all())->toBe([$product->id])
        ->and($product->listings->first()?->is($listing))->toBeTrue()
        ->and($product->ownListing?->is($listing))->toBeTrue()
        ->and($store->listings->first()?->is($listing))->toBeTrue();
});

it('gives an alert rule the owner of its product', function () {
    $rule = AlertRule::factory()->create();

    expect($rule->user_id)->toBe($rule->product->user_id);
});

it('keeps one forecast per listing with its points as an array', function () {
    $forecast = Forecast::factory()->create();

    expect($forecast->listing->forecast?->is($forecast))->toBeTrue()
        ->and($forecast->points)->toHaveCount(14)
        ->and($forecast->points[0])->toHaveKeys(['date', 'price', 'lower', 'upper']);
});

it('deletes listings and price points with their product', function () {
    $listing = Listing::factory()->create();
    $listing->recordPrice(1000, 'EUR');

    $listing->product->delete();

    expect(Listing::query()->count())->toBe(0)
        ->and(PricePoint::query()->count())->toBe(0);
});

it('refuses a second listing for the same product on the same store', function () {
    $listing = Listing::factory()->create();

    Listing::factory()->create([
        'product_id' => $listing->product_id,
        'store_id' => $listing->store_id,
    ]);
})->throws(UniqueConstraintViolationException::class);
