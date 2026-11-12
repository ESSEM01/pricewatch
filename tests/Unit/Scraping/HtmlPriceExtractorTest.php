<?php

use App\Scraping\Extractors\CssSelectorExtractor;
use App\Scraping\Extractors\JsonLdExtractor;
use App\Scraping\Extractors\OpenGraphExtractor;
use App\Scraping\HtmlPriceExtractor;
use App\Scraping\Money\PriceParser;

function htmlFixture(string $name): string
{
    return (string) file_get_contents(fixture("html/{$name}.html"));
}

function extractor(): HtmlPriceExtractor
{
    $parser = new PriceParser;

    return new HtmlPriceExtractor([
        new JsonLdExtractor($parser),
        new OpenGraphExtractor($parser),
        new CssSelectorExtractor($parser),
    ]);
}

it('extracts the price from each kind of page', function (string $fixture, ?string $selector, int $amount, string $currency, string $source) {
    $price = extractor()->extract(htmlFixture($fixture), $selector);

    expect($price)->not->toBeNull()
        ->and($price->amount)->toBe($amount)
        ->and($price->currency)->toBe($currency)
        ->and($price->source)->toBe($source);
})->with([
    'JSON-LD wins over OpenGraph and visible text' => ['jsonld-simple', '.price', 129900, 'EUR', 'json-ld'],
    'JSON-LD in @graph, @type array, first of several offers' => ['jsonld-graph', null, 4990, 'EUR', 'json-ld'],
    'JSON-LD AggregateOffer after a broken block' => ['jsonld-aggregate', null, 19999, 'USD', 'json-ld'],
    'OpenGraph when JSON-LD has no product' => ['opengraph', null, 2499, 'EUR', 'opengraph'],
    'CSS selector as last resort' => ['css-only', '.product-price ins.amount', 129900, 'EUR', 'css'],
]);

it('returns null when no strategy finds a price', function (string $fixture, ?string $selector) {
    expect(extractor()->extract(htmlFixture($fixture), $selector))->toBeNull();
})->with([
    'page without price' => ['no-price', null],
    'selector matching nothing' => ['no-price', '.price'],
    'CSS-only page without a configured selector' => ['css-only', null],
    'invalid CSS selector' => ['css-only', '[[nope'],
]);

it('defaults to EUR when the page states no currency', function () {
    $html = '<html><head><meta property="product:price:amount" content="10.00"></head><body></body></html>';

    expect(extractor()->extract($html, null)?->currency)->toBe('EUR');
});

it('copes with empty or non-HTML input', function (string $html) {
    expect(extractor()->extract($html, '.price'))->toBeNull();
})->with(['', 'just text', '{"price": 10}']);
