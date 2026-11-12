<?php

namespace App\Http\Resources;

/**
 * Money in the API is always integer minor units plus an ISO currency,
 * never a float: { "amount": 49990, "currency": "EUR" } means 499,90 €.
 */
final class MoneyResource
{
    /**
     * @return array{amount: int, currency: string}|null
     */
    public static function make(?int $amount, string $currency): ?array
    {
        return $amount === null ? null : ['amount' => $amount, 'currency' => $currency];
    }
}
