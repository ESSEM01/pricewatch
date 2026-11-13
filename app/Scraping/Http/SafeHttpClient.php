<?php

namespace App\Scraping\Http;

use App\Scraping\Exceptions\ResponseTooLarge;
use App\Scraping\Exceptions\UnsafeUrl;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * The only way the scraper talks to the outside world: every URL and every
 * redirect hop goes through the UrlGuard, and the vetted IP is pinned.
 */
final class SafeHttpClient
{
    public function __construct(private readonly UrlGuard $guard) {}

    /**
     * @param  int|null  $maxBytes  Stop downloading a body larger than this.
     *
     * @throws UnsafeUrl
     * @throws ConnectionException
     * @throws ResponseTooLarge
     */
    public function get(string $url, ?int $maxBytes = null): Response
    {
        $maxRedirects = (int) config('scraping.max_redirects');

        // Redirects are followed here, not by Guzzle: curl would resolve the
        // next host again, unpinned, and on whatever port the server chose.
        for ($redirects = 0; ; $redirects++) {
            $response = $this->send($url, $maxBytes);
            $location = $response->redirect() ? $response->header('Location') : '';

            if ($location === '') {
                return $response;
            }

            if ($redirects >= $maxRedirects) {
                throw new ConnectionException("Too many redirects (more than {$maxRedirects}).");
            }

            $url = $this->resolveLocation($url, $location);
        }
    }

    /**
     * One request, no redirects, connected to the IP the guard vetted.
     *
     * @throws UnsafeUrl
     * @throws ConnectionException
     * @throws ResponseTooLarge
     */
    private function send(string $url, ?int $maxBytes): Response
    {
        $pinnedIp = $this->guard->assertAllowed($url);

        $options = ['allow_redirects' => false];

        if ($pinnedIp !== null) {
            // Connect to the IP we checked, not to whatever DNS says a moment later.
            $options['curl'] = [CURLOPT_RESOLVE => [$this->resolveEntry($url, $pinnedIp)]];
        }

        $tooLarge = null;
        $sink = null;

        if ($maxBytes !== null) {
            // curl reports the declared length as soon as the headers arrive, then
            // the bytes received so far; returning true aborts the transfer.
            $options['progress'] = function (int $downloadTotal, int $downloaded) use ($maxBytes, &$tooLarge): bool {
                $bytes = max($downloadTotal, $downloaded);
                $tooLarge = $bytes > $maxBytes ? $bytes : null;

                return $tooLarge !== null;
            };

            // Those are bytes on the wire: a compressed body is counted again,
            // decoded, as curl writes it to the sink.
            $options['sink'] = $sink = new LimitedSink($maxBytes);
        }

        try {
            return Http::withUserAgent((string) config('scraping.user_agent'))
                ->timeout((int) config('scraping.timeout_seconds'))
                ->connectTimeout((int) config('scraping.connect_timeout_seconds'))
                ->withOptions($options)
                ->get($url);
        } catch (ConnectionException $e) {
            $tooLarge ??= $sink?->overflow();

            throw $tooLarge !== null ? ResponseTooLarge::after($tooLarge) : $e;
        }
    }

    /**
     * @throws UnsafeUrl
     */
    private function resolveLocation(string $base, string $location): string
    {
        try {
            return (string) UriResolver::resolve(new Uri($base), new Uri($location));
        } catch (InvalidArgumentException) {
            throw UnsafeUrl::because($location, 'not a valid redirect target');
        }
    }

    private function resolveEntry(string $url, string $ip): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?? ($scheme === 'https' ? 443 : 80);

        return $host.':'.$port.':'.(str_contains($ip, ':') ? "[{$ip}]" : $ip);
    }
}
