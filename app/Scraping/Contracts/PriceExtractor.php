<?php

namespace App\Scraping\Contracts;

use App\Scraping\Money\ParsedPrice;
use Dom\HTMLDocument;

/**
 * One strategy for finding a product price in an HTML page.
 */
interface PriceExtractor
{
    /** Short name stored with the result, e.g. "json-ld". */
    public function source(): string;

    /**
     * @param  string|null  $selector  Store-specific CSS selector, if configured.
     */
    public function extract(HTMLDocument $document, ?string $selector): ?ParsedPrice;
}
