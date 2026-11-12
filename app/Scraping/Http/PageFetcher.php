<?php

namespace App\Scraping\Http;

use App\Scraping\Exceptions\ScrapeFailed;
use App\Scraping\Exceptions\UnsafeUrl;
use Illuminate\Http\Client\ConnectionException;

/**
 * Downloads a product page politely and safely, turning every expected
 * problem into a ScrapeFailed.
 */
final class PageFetcher
{
    public function __construct(
        private readonly SafeHttpClient $http,
        private readonly RobotsPolicy $robots,
    ) {}

    /**
     * @throws ScrapeFailed
     */
    public function fetch(string $url): string
    {
        try {
            if (! $this->robots->allows($url)) {
                throw ScrapeFailed::disallowedByRobots($url);
            }

            $response = $this->http->get($url);
        } catch (UnsafeUrl $e) {
            throw ScrapeFailed::unsafeUrl($e);
        } catch (ConnectionException $e) {
            throw ScrapeFailed::connection($e);
        }

        if ($response->failed()) {
            throw ScrapeFailed::httpStatus($response->status());
        }

        $body = $response->body();

        if (strlen($body) > (int) config('scraping.max_body_bytes')) {
            throw ScrapeFailed::tooLarge(strlen($body));
        }

        return $body;
    }
}
