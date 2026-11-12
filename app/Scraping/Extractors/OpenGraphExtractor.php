<?php

namespace App\Scraping\Extractors;

use App\Scraping\Contracts\PriceExtractor;
use App\Scraping\Money\ParsedPrice;
use App\Scraping\Money\PriceParser;
use Dom\HTMLDocument;

/**
 * Reads the price from OpenGraph product meta tags (and microdata meta tags).
 */
final class OpenGraphExtractor implements PriceExtractor
{
    private const AMOUNT_SELECTORS = [
        'meta[property="product:price:amount"]',
        'meta[property="og:price:amount"]',
        'meta[itemprop="price"]',
    ];

    private const CURRENCY_SELECTORS = [
        'meta[property="product:price:currency"]',
        'meta[property="og:price:currency"]',
        'meta[itemprop="priceCurrency"]',
    ];

    public function __construct(private readonly PriceParser $parser) {}

    public function source(): string
    {
        return 'opengraph';
    }

    public function extract(HTMLDocument $document, ?string $selector): ?ParsedPrice
    {
        $amount = $this->firstContent($document, self::AMOUNT_SELECTORS);

        if ($amount === null) {
            return null;
        }

        $parsed = $this->parser->parse($amount);

        if ($parsed === null) {
            return null;
        }

        return new ParsedPrice(
            $parsed->amount,
            $this->firstContent($document, self::CURRENCY_SELECTORS) ?? $parsed->currency,
        );
    }

    /**
     * @param  list<string>  $selectors
     */
    private function firstContent(HTMLDocument $document, array $selectors): ?string
    {
        foreach ($selectors as $selector) {
            $content = $document->querySelector($selector)?->getAttribute('content');

            if ($content !== null && trim($content) !== '') {
                return trim($content);
            }
        }

        return null;
    }
}
