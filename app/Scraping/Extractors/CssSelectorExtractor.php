<?php

namespace App\Scraping\Extractors;

use App\Scraping\Contracts\PriceExtractor;
use App\Scraping\Money\ParsedPrice;
use App\Scraping\Money\PriceParser;
use Dom\HTMLDocument;
use DOMException;

/**
 * Last resort: reads the visible text of a store-specific CSS selector.
 * Fragile by nature (it breaks when the shop redesigns), so it runs last.
 */
final class CssSelectorExtractor implements PriceExtractor
{
    public function __construct(private readonly PriceParser $parser) {}

    public function source(): string
    {
        return 'css';
    }

    public function extract(HTMLDocument $document, ?string $selector): ?ParsedPrice
    {
        if ($selector === null || trim($selector) === '') {
            return null;
        }

        try {
            $element = $document->querySelector($selector);
        } catch (DOMException) {
            return null; // an invalid selector saved on the store must not crash the scrape
        }

        $text = $element?->textContent;

        return $text === null ? null : $this->parser->parse(trim($text));
    }
}
