<?php

namespace App\Scraping;

/**
 * A price found on a page, with a definite currency and the strategy that found it.
 */
final readonly class ExtractedPrice
{
    public function __construct(
        public int $amount,
        public string $currency,
        public string $source,
    ) {}
}
