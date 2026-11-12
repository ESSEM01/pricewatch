<?php

namespace App\Scraping;

use App\Models\Listing;
use App\Scraping\Exceptions\ScrapeFailed;
use App\Scraping\Http\PageFetcher;

/**
 * Fetches a listing's page and extracts its price.
 */
final class PriceScraper
{
    public function __construct(
        private readonly PageFetcher $fetcher,
        private readonly HtmlPriceExtractor $extractor,
    ) {}

    /**
     * @throws ScrapeFailed
     */
    public function scrape(Listing $listing): ExtractedPrice
    {
        $html = $this->fetcher->fetch($listing->url);

        return $this->extractor->extract($html, $listing->store->price_selector)
            ?? throw ScrapeFailed::noPrice();
    }
}
