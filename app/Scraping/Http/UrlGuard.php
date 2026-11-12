<?php

namespace App\Scraping\Http;

use App\Scraping\Exceptions\UnsafeUrl;
use Closure;

/**
 * SSRF protection. Users paste URLs that the server then fetches, so every
 * URL (and every redirect hop) must point to a public address on a web port.
 */
final class UrlGuard
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const ALLOWED_PORTS = [80, 443];

    /** @var list<string> */
    private readonly array $trustedOrigins;

    /** @var Closure(string): list<string> */
    private readonly Closure $resolve;

    /**
     * @param  list<string>  $trustedOrigins  e.g. the app's own URL, so the demo shop works locally.
     * @param  (Closure(string): list<string>)|null  $resolve  Host to IPs; injectable for tests.
     */
    public function __construct(array $trustedOrigins = [], ?Closure $resolve = null)
    {
        $this->trustedOrigins = array_values(array_filter(array_map(self::origin(...), $trustedOrigins)));
        $this->resolve = $resolve ?? self::resolveWithSystemDns(...);
    }

    /**
     * Throws if the URL is unsafe; otherwise returns the vetted IP to connect
     * to (pinning it defeats DNS rebinding), or null for a trusted origin.
     *
     * @throws UnsafeUrl
     */
    public function assertAllowed(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
            throw UnsafeUrl::because($url, 'not an absolute URL');
        }

        if (! in_array(strtolower($parts['scheme']), self::ALLOWED_SCHEMES, true)) {
            throw UnsafeUrl::because($url, 'only http and https are allowed');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw UnsafeUrl::because($url, 'credentials in URLs are not allowed');
        }

        if (in_array(self::origin($url), $this->trustedOrigins, true)) {
            return null;
        }

        if (isset($parts['port']) && ! in_array($parts['port'], self::ALLOWED_PORTS, true)) {
            throw UnsafeUrl::because($url, 'only ports 80 and 443 are allowed');
        }

        $host = strtolower(trim($parts['host'], '[]'));
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolve)($host);

        if ($ips === []) {
            throw UnsafeUrl::because($url, 'the host does not resolve');
        }

        foreach ($ips as $ip) {
            // Rejects loopback, private, link-local (cloud metadata), CGNAT, reserved...
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                throw UnsafeUrl::because($url, 'it points to a non-public address');
            }
        }

        return $ips[0];
    }

    /**
     * Normalized "scheme://host:port", or null when the URL has no host.
     */
    public static function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        return $scheme.'://'.strtolower($parts['host']).':'.$port;
    }

    /**
     * @return list<string>
     */
    private static function resolveWithSystemDns(string $host): array
    {
        $ipv4 = gethostbynamel($host) ?: [];
        $records = @dns_get_record($host, DNS_AAAA) ?: [];
        $ipv6 = array_values(array_filter(array_column($records, 'ipv6'), 'is_string'));

        return array_values(array_unique([...$ipv4, ...$ipv6]));
    }
}
