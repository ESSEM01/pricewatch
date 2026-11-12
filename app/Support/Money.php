<?php

namespace App\Support;

/**
 * Formats integer cents for display ("1.299,00 €"). Hand-rolled because the
 * intl extension is not guaranteed on every PHP install.
 */
final class Money
{
    private const SYMBOLS = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'];

    public static function format(?int $cents, string $currency = 'EUR'): string
    {
        if ($cents === null) {
            return '—';
        }

        return number_format($cents / 100, 2, ',', '.').' '.(self::SYMBOLS[$currency] ?? $currency);
    }
}
