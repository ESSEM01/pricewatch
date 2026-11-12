<?php

namespace App\Scraping\Money;

/**
 * A price read from a web page, in integer minor units (cents).
 */
final readonly class ParsedPrice
{
    public function __construct(
        public int $amount,
        public ?string $currency,
    ) {}
}
