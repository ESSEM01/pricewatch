<?php

/*
|--------------------------------------------------------------------------
| Scraping
|--------------------------------------------------------------------------
|
| How PriceWatch fetches product pages. The bot identifies itself, respects
| robots.txt, never reaches private networks and spreads requests per store.
|
*/

return [

    // Token matched against robots.txt "User-agent" groups.
    'robots_agent' => 'PriceWatchBot',

    'user_agent' => env(
        'SCRAPER_USER_AGENT',
        'PriceWatchBot/1.0 (+'.rtrim((string) env('APP_URL', 'http://localhost'), '/').'/bot)',
    ),

    'timeout_seconds' => 10,
    'connect_timeout_seconds' => 5,
    'max_redirects' => 3,
    'max_body_bytes' => 5 * 1024 * 1024,
    'robots_cache_hours' => 24,
    // A robots.txt that errors out blocks the site for this long, then is retried.
    'robots_error_cache_minutes' => 60,

    // Scheduling.
    'check_interval_hours' => 6,
    'retry_after_minutes' => 15,
    'failures_before_backoff' => 3,
    'backoff_hours' => 6,
    'requests_per_minute_per_store' => 6,

    // Origins (scheme://host:port) allowed even if private, besides APP_URL,
    // which is always trusted so the built-in demo shop can be scraped.
    'trusted_origins' => array_values(array_filter(explode(',', (string) env('SCRAPER_TRUSTED_ORIGINS', '')))),

];
