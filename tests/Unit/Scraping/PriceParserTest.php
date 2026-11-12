<?php

use App\Scraping\Money\ParsedPrice;
use App\Scraping\Money\PriceParser;

dataset('valid prices', [
    'plain decimal (JSON-LD style)' => ['1299.00', 129900, null],
    'integer string' => ['1299', 129900, null],
    'italian format with euro sign' => ['1.299,00 €', 129900, 'EUR'],
    'english format with euro sign' => ['€ 1,299.99', 129999, 'EUR'],
    'space as thousands separator' => ['1 299,00 €', 129900, 'EUR'],
    'non-breaking space as thousands separator' => ["1\u{00A0}299,00\u{00A0}€", 129900, 'EUR'],
    'single decimal digit' => ['19,9', 1990, null],
    'dollar prefix' => ['$12.50', 1250, 'USD'],
    'pound prefix' => ['£ 9.99', 999, 'GBP'],
    'ISO code prefix' => ['EUR 49,90', 4990, 'EUR'],
    'ISO code suffix, lowercase' => ['49,90 eur', 4990, 'EUR'],
    'dot followed by 3 digits is a thousands separator' => ['1.299', 129900, null],
    'comma followed by 3 digits is a thousands separator' => ['1,299', 129900, null],
    'multiple thousands separators' => ['1.234.567,89', 123456789, null],
    'short decimal with dot' => ['12.5', 1250, null],
    'zero' => ['0,00', 0, null],
    'surrounding text' => ['Prezzo: 24,99 € IVA inclusa', 2499, 'EUR'],
    'leading zero decimal' => ['0.5', 50, null],
    'extra decimals are rounded half up' => ['12.345,678', 1234568, null],
]);

it('parses a price string into integer cents', function (string $raw, int $cents, ?string $currency) {
    $parsed = (new PriceParser)->parse($raw);

    expect($parsed)->toBeInstanceOf(ParsedPrice::class)
        ->and($parsed->amount)->toBe($cents)
        ->and($parsed->currency)->toBe($currency);
})->with('valid prices');

it('accepts numeric values as they come from JSON-LD', function (int|float $raw, int $cents) {
    expect((new PriceParser)->parse($raw)?->amount)->toBe($cents);
})->with([
    'float' => [1299.99, 129999],
    'float with binary rounding error' => [0.29, 29],
    'int' => [42, 4200],
]);

it('returns null when there is no usable price', function (string|float $raw) {
    expect((new PriceParser)->parse($raw))->toBeNull();
})->with([
    'empty' => [''],
    'only text' => ['Gratis'],
    'only a currency' => ['€'],
    'negative number' => ['-5,00'],
    'negative float' => [-5.0],
    'two separate numbers' => ['da 10,00 a 20,00 €'],
]);

it('uses the fallback currency when none is detected', function () {
    $parsed = (new PriceParser)->parse('12,00', fallbackCurrency: 'EUR');

    expect($parsed?->currency)->toBe('EUR');
});

it('prefers the detected currency over the fallback', function () {
    $parsed = (new PriceParser)->parse('$12.00', fallbackCurrency: 'EUR');

    expect($parsed?->currency)->toBe('USD');
});
