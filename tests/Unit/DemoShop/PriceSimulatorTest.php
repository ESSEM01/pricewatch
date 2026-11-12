<?php

use App\DemoShop\DemoProduct;
use App\DemoShop\DemoStore;
use App\DemoShop\PriceSimulator;
use App\DemoShop\PricingStrategy;
use Carbon\CarbonImmutable;

function simulator(): PriceSimulator
{
    return new PriceSimulator(CarbonImmutable::parse('2026-01-01'));
}

function demoStore(PricingStrategy $strategy, float $markup = 1.0): DemoStore
{
    return new DemoStore('test-store', 'Test Store', $strategy, $markup);
}

function demoProduct(int $basePrice = 100_000): DemoProduct
{
    return new DemoProduct('test-product', 'Test Product', $basePrice);
}

/**
 * @return list<int>
 */
function pricesOver(DemoStore $store, DemoProduct $product, string $from, int $days): array
{
    $start = CarbonImmutable::parse($from);

    return array_map(
        fn (int $i) => simulator()->priceFor($store, $product, $start->addDays($i)),
        range(0, $days - 1),
    );
}

it('is deterministic', function (PricingStrategy $strategy) {
    $date = CarbonImmutable::parse('2026-03-14 15:30');

    expect(simulator()->priceFor(demoStore($strategy), demoProduct(), $date))
        ->toBe(simulator()->priceFor(demoStore($strategy), demoProduct(), $date->setTime(8, 0)));
})->with(PricingStrategy::cases());

it('always ends prices in 90 cents', function (PricingStrategy $strategy) {
    foreach (pricesOver(demoStore($strategy), demoProduct(54_999), '2026-02-01', 60) as $price) {
        expect($price % 100)->toBe(90);
    }
})->with(PricingStrategy::cases());

it('applies the store markup', function () {
    $date = CarbonImmutable::parse('2026-03-04'); // a Wednesday, never a promo day

    $plain = simulator()->priceFor(demoStore(PricingStrategy::WeekendPromo), demoProduct(), $date);
    $marked = simulator()->priceFor(demoStore(PricingStrategy::WeekendPromo, 1.10), demoProduct(), $date);

    expect($plain)->toBe(99_990)->and($marked)->toBe(109_990);
});

it('discounts 12% on Friday to Sunday every other week only', function () {
    $start = CarbonImmutable::parse('2026-03-02'); // Monday
    $prices = pricesOver(demoStore(PricingStrategy::WeekendPromo), demoProduct(), $start->toDateString(), 14);

    $discountedDays = array_keys(array_filter($prices, fn (int $p) => $p < 99_990));
    $weekdays = array_map(fn (int $i) => $start->addDays($i)->isoWeekday(), $discountedDays);

    expect($discountedDays)->toHaveCount(3)
        ->and($weekdays)->toBe([5, 6, 7])
        ->and(array_unique(array_map(fn (int $i) => $prices[$i], $discountedDays)))->toBe([$prices[$discountedDays[0]]])
        ->and($prices[$discountedDays[0]])->toBe(87_990);
});

it('lowers prices within a 90-day cycle', function () {
    $store = demoStore(PricingStrategy::TrendDown);

    $early = simulator()->priceFor($store, demoProduct(), CarbonImmutable::parse('2026-01-05'));
    $later = simulator()->priceFor($store, demoProduct(), CarbonImmutable::parse('2026-03-05'));

    expect($later)->toBeLessThan($early);
});

it('resets the trend every 90 days and never drops below 80%', function () {
    $prices = pricesOver(demoStore(PricingStrategy::TrendDown), demoProduct(), '2026-01-01', 365);

    $rises = 0;
    foreach (array_slice($prices, 1, preserve_keys: true) as $i => $price) {
        $rises += $price > $prices[$i - 1] ? 1 : 0;
    }

    expect(min($prices))->toBeGreaterThanOrEqual(79_990)
        ->and($rises)->toBe(4); // days 90, 180, 270, 360
});

it('keeps volatile prices within 5% and stable for 3 days at a time', function () {
    $prices = pricesOver(demoStore(PricingStrategy::Volatile), demoProduct(), '2026-01-01', 30);

    foreach ($prices as $price) {
        expect($price)->toBeGreaterThanOrEqual(94_990)->toBeLessThanOrEqual(104_990);
    }

    foreach (array_chunk($prices, 3) as $bucket) {
        expect(array_unique($bucket))->toHaveCount(1);
    }

    expect(count(array_unique($prices)))->toBeGreaterThan(3);
});

it('handles dates before the anchor without errors', function (PricingStrategy $strategy) {
    $price = simulator()->priceFor(demoStore($strategy), demoProduct(), CarbonImmutable::parse('2025-06-01'));

    expect($price)->toBeGreaterThan(0);
})->with(PricingStrategy::cases());
