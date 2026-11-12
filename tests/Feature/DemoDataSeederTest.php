<?php

use App\DemoShop\DemoCatalog;
use App\DemoShop\PriceSimulator;
use App\Models\Forecast;
use App\Models\Listing;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12 10:00:00');
});

it('creates a demo user with 10 products on 3 stores and 180 days of history', function () {
    $this->seed(DemoDataSeeder::class);

    $user = User::query()->where('email', DemoDataSeeder::EMAIL)->sole();

    expect($user->products()->count())->toBeGreaterThanOrEqual(10)
        ->and(Store::query()->count())->toBe(3)
        ->and(Listing::query()->count())->toBeGreaterThanOrEqual(30);

    Listing::query()->withCount('pricePoints')->get()->each(function (Listing $listing) {
        expect($listing->getAttribute('price_points_count'))->toBe(DemoDataSeeder::HISTORY_DAYS);
    });
});

it('marks one store as the user\'s own on every product', function () {
    $this->seed(DemoDataSeeder::class);

    Product::query()->with('listings')->get()->each(function (Product $product) {
        expect($product->listings->where('is_own', true))->toHaveCount(1);
    });
});

it('ends each history at today\'s simulated price and queues a check', function () {
    $this->seed(DemoDataSeeder::class);

    $catalog = app(DemoCatalog::class);
    $store = $catalog->stores()[1];
    $demoProduct = $catalog->products()[0];
    $listing = Listing::query()
        ->whereHas('store', fn ($q) => $q->where('domain', "{$store->slug}.demo-shop"))
        ->whereHas('product', fn ($q) => $q->where('name', $demoProduct->name))
        ->sole();

    expect($listing->current_price)->toBe(app(PriceSimulator::class)->priceFor($store, $demoProduct, now()))
        ->and($listing->pricePoints()->max('recorded_at'))->toStartWith('2026-11-12')
        ->and($listing->pricePoints()->min('recorded_at'))->toStartWith('2026-05-17') // 180 days including today
        ->and(Listing::query()->due()->count())->toBe(Listing::query()->count());
});

it('precomputes a forecast for every listing', function () {
    $this->seed(DemoDataSeeder::class);

    expect(Forecast::query()->count())->toBe(Listing::query()->count());
});

it('can run twice without duplicating data', function () {
    $this->seed(DemoDataSeeder::class);
    $this->seed(DemoDataSeeder::class);

    expect(User::query()->count())->toBe(1)
        ->and(Product::query()->count())->toBe(count(app(DemoCatalog::class)->products()))
        ->and(Store::query()->count())->toBe(3);
});
