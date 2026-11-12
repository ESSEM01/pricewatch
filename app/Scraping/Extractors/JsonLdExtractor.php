<?php

namespace App\Scraping\Extractors;

use App\Scraping\Contracts\PriceExtractor;
use App\Scraping\Money\ParsedPrice;
use App\Scraping\Money\PriceParser;
use Dom\HTMLDocument;
use Generator;

/**
 * Reads schema.org Product data from <script type="application/ld+json">.
 * The most reliable source: most shops publish it for Google Shopping.
 */
final class JsonLdExtractor implements PriceExtractor
{
    public function __construct(private readonly PriceParser $parser) {}

    public function source(): string
    {
        return 'json-ld';
    }

    public function extract(HTMLDocument $document, ?string $selector): ?ParsedPrice
    {
        foreach ($document->querySelectorAll('script[type="application/ld+json"]') as $script) {
            $data = json_decode($script->textContent ?? '', true);

            if (! is_array($data)) {
                continue; // broken JSON is common; try the next block
            }

            foreach ($this->products($data) as $product) {
                $price = $this->fromOffers($product['offers'] ?? null);

                if ($price !== null) {
                    return $price;
                }
            }
        }

        return null;
    }

    /**
     * Walks top-level arrays and @graph containers looking for Product nodes.
     *
     * @param  array<mixed>  $node
     * @return Generator<array<mixed>>
     */
    private function products(array $node): Generator
    {
        if ($this->isProduct($node)) {
            yield $node;
        }

        $children = array_is_list($node) ? $node : ($node['@graph'] ?? []);

        foreach (is_array($children) ? $children : [] as $child) {
            if (is_array($child)) {
                yield from $this->products($child);
            }
        }
    }

    /**
     * @param  array<mixed>  $node
     */
    private function isProduct(array $node): bool
    {
        $types = (array) ($node['@type'] ?? []);

        foreach ($types as $type) {
            // Accept both "Product" and "https://schema.org/Product".
            if (is_string($type) && preg_match('#(^|/)Product$#', $type) === 1) {
                return true;
            }
        }

        return false;
    }

    private function fromOffers(mixed $offers): ?ParsedPrice
    {
        if (! is_array($offers)) {
            return null;
        }

        if (array_is_list($offers)) {
            foreach ($offers as $offer) {
                if (($price = $this->fromOffers($offer)) !== null) {
                    return $price;
                }
            }

            return null;
        }

        // Offer.price, AggregateOffer.lowPrice, or a nested PriceSpecification.
        $specification = is_array($offers['priceSpecification'] ?? null) ? $offers['priceSpecification'] : [];
        $raw = $offers['price'] ?? $offers['lowPrice'] ?? $specification['price'] ?? null;
        $currency = $offers['priceCurrency'] ?? $specification['priceCurrency'] ?? null;

        if (! is_string($raw) && ! is_int($raw) && ! is_float($raw)) {
            return null;
        }

        $parsed = $this->parser->parse($raw);

        if ($parsed === null) {
            return null;
        }

        // An explicit priceCurrency beats anything guessed from the text.
        return new ParsedPrice($parsed->amount, is_string($currency) ? $currency : $parsed->currency);
    }
}
