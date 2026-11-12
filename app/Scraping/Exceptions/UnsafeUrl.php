<?php

namespace App\Scraping\Exceptions;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A URL the scraper must not fetch (private network, odd scheme, ...).
 */
final class UnsafeUrl extends InvalidArgumentException
{
    public static function because(string $url, string $reason): self
    {
        return new self(sprintf('Refusing to fetch "%s": %s.', Str::limit($url, 200), $reason));
    }
}
