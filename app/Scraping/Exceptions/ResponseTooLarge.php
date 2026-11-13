<?php

namespace App\Scraping\Exceptions;

use RuntimeException;

/**
 * A response body over the size limit; the download was stopped there.
 */
final class ResponseTooLarge extends RuntimeException
{
    private function __construct(public readonly int $bytes)
    {
        parent::__construct("The response is too large ({$bytes} bytes).");
    }

    /**
     * @param  int  $bytes  The declared length, or what was downloaded before stopping.
     */
    public static function after(int $bytes): self
    {
        return new self($bytes);
    }
}
