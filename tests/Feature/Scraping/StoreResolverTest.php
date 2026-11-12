<?php

use App\Models\Store;
use App\Scraping\StoreResolver;

function resolveStore(string $url): Store
{
    return app(StoreResolver::class)->resolve($url);
}

it('maps each demo shop store to its own store record', function () {
    $store = resolveStore(rtrim(config('app.url'), '/').'/demo-shop/tecnoshop/ps5-slim');

    expect($store->name)->toBe('TecnoShop')
        ->and($store->domain)->toBe('tecnoshop.demo-shop');
});

it('reuses the store for the same domain, ignoring www', function () {
    $first = resolveStore('https://www.shop.example/p/1');
    $second = resolveStore('https://shop.example/p/2');

    expect($second->is($first))->toBeTrue()
        ->and($first->domain)->toBe('shop.example')
        ->and(Store::query()->count())->toBe(1);
});

it('does not treat a demo-shop path on another host as the demo shop', function () {
    expect(resolveStore('https://shop.example/demo-shop/tecnoshop/ps5-slim')->domain)->toBe('shop.example');
});

it('falls back to the host for an unknown demo store', function () {
    $url = rtrim(config('app.url'), '/').'/demo-shop/unknown/ps5-slim';

    expect(resolveStore($url)->domain)->toBe(strtolower((string) parse_url($url, PHP_URL_HOST)));
});
