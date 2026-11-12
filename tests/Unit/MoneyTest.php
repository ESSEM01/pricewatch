<?php

use App\Support\Money;

it('formats cents the Italian way', function (int $cents, string $currency, string $expected) {
    expect(Money::format($cents, $currency))->toBe($expected);
})->with([
    [129900, 'EUR', '1.299,00 €'],
    [990, 'EUR', '9,90 €'],
    [0, 'EUR', '0,00 €'],
    [1250, 'USD', '12,50 $'],
    [1250, 'CHF', '12,50 CHF'],
]);

it('shows a dash for a missing price', function () {
    expect(Money::format(null))->toBe('—');
});
