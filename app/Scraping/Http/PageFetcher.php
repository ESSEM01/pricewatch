<?php

namespace App\Scraping\Http;

use App\Scraping\Exceptions\ResponseTooLarge;
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
        $maxBytes = (int) config('scraping.max_body_bytes');

        try {
            if (! $this->robots->allows($url)) {
                throw ScrapeFailed::disallowedByRobots($url);
            }

            // The client stops the transfer once it passes the limit.
            $response = $this->http->get($url, $maxBytes);
        } catch (UnsafeUrl $e) {
            throw ScrapeFailed::unsafeUrl($e);
        } catch (ResponseTooLarge $e) {
            throw ScrapeFailed::tooLarge($e->bytes);
        } catch (ConnectionException $e) {
            throw ScrapeFailed::connection($e);
        }

        if ($response->failed()) {
            throw ScrapeFailed::httpStatus($response->status());
        }

        $declared = (int) $response->header('Content-Length');

        if ($declared > $maxBytes) {
            throw ScrapeFailed::tooLarge($declared);
        }

        $body = $response->body();

        // Last line of defence, e.g. for a body that grew while being decompressed.
        if (strlen($body) > $maxBytes) {
            throw ScrapeFailed::tooLarge(strlen($body));
        }

        return $body;
    }
}
