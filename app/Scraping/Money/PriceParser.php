<?php

namespace App\Scraping\Money;

/**
 * Turns the many ways shops print a price ("1.299,00 €", "€ 1,299.99",
 * "1299.00") into integer cents. Money never goes through a float here,
 * except when the source value already is one (JSON-LD numbers).
 */
final class PriceParser
{
    /** @var array<string, string> */
    private const SYMBOLS = ['€' => 'EUR', '$' => 'USD', '£' => 'GBP'];

    private const ISO_CODES = '/\b(EUR|USD|GBP|CHF)\b/i';

    /**
     * A number token: digits, dots and commas, plus single spaces used as
     * thousands separators ("1 299,00").
     */
    private const NUMBER_TOKEN = '/-?\d(?:[\d.,]|\s(?=\d{3}\b))*/u';

    /** Guards against integer overflow on absurd inputs. */
    private const MAX_INTEGER_DIGITS = 9;

    public function parse(string|int|float $raw, ?string $fallbackCurrency = null): ?ParsedPrice
    {
        if (! is_string($raw)) {
            return $this->fromNumber($raw, $fallbackCurrency);
        }

        $text = str_replace(["\u{00A0}", "\u{202F}"], ' ', $raw);

        if (preg_match_all(self::NUMBER_TOKEN, $text, $matches) !== 1) {
            return null; // no number, or an ambiguous range like "da 10 a 20"
        }

        $token = rtrim($matches[0][0], '.,');

        if (str_starts_with($token, '-')) {
            return null;
        }

        $amount = $this->toCents($this->normalize(str_replace(' ', '', $token)));

        if ($amount === null) {
            return null;
        }

        return new ParsedPrice($amount, $this->detectCurrency($text) ?? $fallbackCurrency);
    }

    private function fromNumber(int|float $raw, ?string $fallbackCurrency): ?ParsedPrice
    {
        if ($raw < 0 || ! is_finite((float) $raw)) {
            return null;
        }

        return new ParsedPrice((int) round($raw * 100), $fallbackCurrency);
    }

    /**
     * Rewrites a token so that "." is the only, optional, decimal separator.
     */
    private function normalize(string $number): string
    {
        $lastDot = strrpos($number, '.');
        $lastComma = strrpos($number, ',');

        if ($lastDot !== false && $lastComma !== false) {
            // Both present: whichever comes last is the decimal separator.
            [$decimal, $thousands] = $lastDot > $lastComma ? ['.', ','] : [',', '.'];

            return str_replace([$thousands, $decimal], ['', '.'], $number);
        }

        $separator = $lastDot !== false ? '.' : ($lastComma !== false ? ',' : null);

        if ($separator === null) {
            return $number;
        }

        $digitsAfterLast = strlen($number) - (int) strrpos($number, $separator) - 1;

        // "1.299" and "1,299" are thousands; "12.5" and "19,90" are decimals.
        $isThousands = substr_count($number, $separator) > 1 || $digitsAfterLast === 3;

        return str_replace($separator, $isThousands ? '' : '.', $number);
    }

    private function toCents(string $number): ?int
    {
        if (preg_match('/^(\d+)(?:\.(\d+))?$/', $number, $parts) !== 1) {
            return null;
        }

        $integer = ltrim($parts[1], '0');

        if (strlen($integer) > self::MAX_INTEGER_DIGITS) {
            return null;
        }

        // Keep three fraction digits and round half up on the third.
        $fraction = (int) substr(str_pad($parts[2] ?? '', 3, '0'), 0, 3);

        return (int) $integer * 100 + intdiv($fraction + 5, 10);
    }

    private function detectCurrency(string $text): ?string
    {
        foreach (self::SYMBOLS as $symbol => $code) {
            if (str_contains($text, $symbol)) {
                return $code;
            }
        }

        if (preg_match(self::ISO_CODES, $text, $match) === 1) {
            return strtoupper($match[1]);
        }

        return null;
    }
}
