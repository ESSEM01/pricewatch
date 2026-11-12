<?php

namespace App\Scraping\Http;

use App\Scraping\Exceptions\UnsafeUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * The only way the scraper talks to the outside world: every URL and every
 * redirect hop goes through the UrlGuard, and the vetted IP is pinned.
 */
final class SafeHttpClient
{
    public function __construct(private readonly UrlGuard $guard) {}

    /**
     * @throws UnsafeUrl
     * @throws ConnectionException
     */
    public function get(string $url): Response
    {
        $pinnedIp = $this->guard->assertAllowed($url);

        $options = [
            'allow_redirects' => [
                'max' => (int) config('scraping.max_redirects'),
                'protocols' => ['http', 'https'],
                'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $next): void {
                    $this->guard->assertAllowed((string) $next);
                },
            ],
        ];

        if ($pinnedIp !== null) {
            // Connect to the IP we checked, not to whatever DNS says a moment later.
            $options['curl'] = [CURLOPT_RESOLVE => [$this->resolveEntry($url, $pinnedIp)]];
        }

        return Http::withUserAgent((string) config('scraping.user_agent'))
            ->timeout((int) config('scraping.timeout_seconds'))
            ->connectTimeout((int) config('scraping.connect_timeout_seconds'))
            ->withOptions($options)
            ->get($url);
    }

    private function resolveEntry(string $url, string $ip): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?? ($scheme === 'https' ? 443 : 80);

        return $host.':'.$port.':'.(str_contains($ip, ':') ? "[{$ip}]" : $ip);
    }
}
