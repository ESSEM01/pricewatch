<?php

namespace App\Scraping\Http;

use App\Scraping\Exceptions\ResponseTooLarge;
use App\Scraping\Exceptions\UnsafeUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;

/**
 * Decides whether a URL may be fetched according to its site's robots.txt,
 * cached per origin.
 */
final class RobotsPolicy
{
    /** Cache marker for "robots.txt could not be read": treat as disallow-all. */
    private const UNREADABLE = "\0unreadable";

    public function __construct(private readonly SafeHttpClient $http) {}

    /**
     * @throws UnsafeUrl
     */
    public function allows(string $url): bool
    {
        $parts = parse_url($url);
        $path = ($parts['path'] ?? '/') ?: '/';
        $path .= isset($parts['query']) ? '?'.$parts['query'] : '';

        return $this->robotsFor($url)->allows((string) config('scraping.robots_agent'), $path);
    }

    private function robotsFor(string $url): RobotsTxt
    {
        $origin = (string) UrlGuard::origin($url);
        $key = 'robots:'.sha1($origin);
        $cached = Cache::get($key);

        if (is_string($cached)) {
            return $cached === self::UNREADABLE ? RobotsTxt::disallowAll() : RobotsTxt::parse($cached);
        }

        $robotsUrl = rtrim($this->baseUrl($url), '/').'/robots.txt';

        try {
            $response = $this->http->get($robotsUrl, (int) config('scraping.robots_max_bytes'));

            // No robots.txt (4xx) means everything is allowed (RFC 9309 §2.3.1.3).
            if ($response->successful() || $response->clientError()) {
                $body = $response->successful() ? $response->body() : '';
                Cache::put($key, $body, now()->addHours((int) config('scraping.robots_cache_hours')));

                return RobotsTxt::parse($body);
            }
        } catch (ConnectionException|ResponseTooLarge) {
            // fall through: unreachable or oversized counts as disallowed
        }

        // 5xx, unreachable or oversized: assume complete disallow, retry later (RFC 9309 §2.3.1.4).
        Cache::put($key, self::UNREADABLE, now()->addMinutes((int) config('scraping.robots_error_cache_minutes')));

        return RobotsTxt::disallowAll();
    }

    private function baseUrl(string $url): string
    {
        $parts = parse_url($url);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').$port;
    }
}
