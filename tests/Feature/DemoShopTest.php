<?php

use App\DemoShop\DemoCatalog;
use App\DemoShop\PriceSimulator;
use App\Scraping\Money\PriceParser;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12 10:00:00');
});

/**
 * @return array<string, mixed>
 */
function jsonLdFrom(string $html): array
{
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match);

    return json_decode($match[1] ?? 'null', true, flags: JSON_THROW_ON_ERROR);
}

it('lists every store and product combination', function () {
    $catalog = app(DemoCatalog::class);
    $response = $this->get(route('demo-shop.index'))->assertOk();

    foreach ($catalog->stores() as $store) {
        foreach ($catalog->products() as $product) {
            $response->assertSee(route('demo-shop.product', [$store->slug, $product->slug]), false);
        }
    }
});

it('exposes the simulated price as JSON-LD, OpenGraph and visible text', function () {
    $catalog = app(DemoCatalog::class);
    $store = $catalog->stores()[0];
    $product = $catalog->products()[0];
    $expected = app(PriceSimulator::class)->priceFor($store, $product, now());

    $response = $this->get(route('demo-shop.product', [$store->slug, $product->slug]))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $html = $response->getContent();
    $jsonLd = jsonLdFrom($html);
    $amount = number_format($expected / 100, 2, '.', '');

    expect($jsonLd['@type'])->toBe('Product')
        ->and($jsonLd['name'])->toBe($product->name)
        ->and($jsonLd['offers']['price'])->toBe($amount)
        ->and($jsonLd['offers']['priceCurrency'])->toBe('EUR')
        ->and($html)->toContain('<meta property="product:price:amount" content="'.$amount.'">');

    preg_match('#<span class="price">(.*?)</span>#u', $html, $visible);
    expect((new PriceParser)->parse(html_entity_decode($visible[1]))?->amount)->toBe($expected);
});

it('changes price over time for a store with a downward trend', function () {
    $url = route('demo-shop.product', ['elettrocasa', 'iphone-16-128']);

    $now = jsonLdFrom($this->get($url)->getContent())['offers']['price'];
    Carbon::setTestNow('2026-11-30 10:00:00'); // same 90-day cycle as Nov 12
    $later = jsonLdFrom($this->get($url)->getContent())['offers']['price'];

    expect((float) $later)->toBeLessThan((float) $now);
});

it('returns 404 for unknown stores or products', function (string $store, string $product) {
    $this->get("/demo-shop/{$store}/{$product}")->assertNotFound();
})->with([
    'unknown store' => ['nope', 'iphone-16-128'],
    'unknown product' => ['tecnoshop', 'nope'],
]);
