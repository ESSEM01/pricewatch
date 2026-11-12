<?php

namespace App\Enums;

enum ListingStatus: string
{
    /** Scraped on schedule. */
    case Active = 'active';

    /** Too many consecutive scrape errors; retried with a back-off. */
    case Failing = 'failing';

    /** Paused by the user; never scraped. */
    case Paused = 'paused';
}
