<?php

namespace App\Scraping\Exceptions;

use RuntimeException;
use Throwable;

/**
 * An expected scrape failure. Permanent failures (blocked URL, product gone)
 * send the listing straight into back-off instead of retrying soon.
 */
final class ScrapeFailed extends RuntimeException
{
    private function __construct(string $message, public readonly bool $permanent, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function unsafeUrl(UnsafeUrl $e): self
    {
        return new self($e->getMessage(), permanent: true, previous: $e);
    }

    public static function disallowedByRobots(string $url): self
    {
        return new self('The site\'s robots.txt does not allow fetching this page.', permanent: true);
    }

    public static function connection(Throwable $e): self
    {
        return new self('Could not connect to the site: '.$e->getMessage(), permanent: false, previous: $e);
    }

    public static function httpStatus(int $status): self
    {
        // 404/410: the product page no longer exists; retrying soon is pointless.
        return new self("The site answered with HTTP {$status}.", permanent: in_array($status, [404, 410], true));
    }

    public static function tooLarge(int $bytes): self
    {
        return new self("The page is too large ({$bytes} bytes).", permanent: false);
    }

    public static function noPrice(): self
    {
        return new self('No price found on the page.', permanent: false);
    }
}
