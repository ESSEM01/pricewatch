<?php

namespace App\Scraping;

use App\Scraping\Contracts\PriceExtractor;
use Dom\HTMLDocument;

/**
 * Tries each extraction strategy in order of reliability and returns the
 * first price found.
 */
final class HtmlPriceExtractor
{
    /**
     * @param  list<PriceExtractor>  $extractors  Most reliable first.
     */
    public function __construct(
        private readonly array $extractors,
        private readonly string $defaultCurrency = 'EUR',
    ) {}

    public function extract(string $html, ?string $selector): ?ExtractedPrice
    {
        if (trim($html) === '') {
            return null;
        }

        // The PHP 8.4+ HTML5 parser: tolerant of real-world markup, native CSS selectors.
        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

        foreach ($this->extractors as $extractor) {
            $price = $extractor->extract($document, $selector);

            if ($price !== null) {
                return new ExtractedPrice($price->amount, $this->currency($price->currency), $extractor->source());
            }
        }

        return null;
    }

    private function currency(?string $currency): string
    {
        $code = strtoupper(trim((string) $currency));

        return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : $this->defaultCurrency;
    }
}
