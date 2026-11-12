<?php

use App\Scraping\Exceptions\UnsafeUrl;
use App\Scraping\Http\UrlGuard;

/**
 * A guard with a fake DNS, so tests never touch the network.
 *
 * @param  array<string, list<string>>  $dns
 */
function urlGuard(array $dns = []): UrlGuard
{
    return new UrlGuard(
        trustedOrigins: ['http://127.0.0.1:8000'],
        resolve: fn (string $host): array => $dns[$host] ?? [],
    );
}

it('allows public hosts and returns the IP to pin', function () {
    $ip = urlGuard(['shop.example' => ['93.184.216.34']])->assertAllowed('https://shop.example/p/1?color=red');

    expect($ip)->toBe('93.184.216.34');
});

it('allows public IP literals, v4 and v6', function (string $url, string $ip) {
    expect(urlGuard()->assertAllowed($url))->toBe($ip);
})->with([
    ['http://93.184.216.34/', '93.184.216.34'],
    ['https://[2606:4700:4700::1111]/', '2606:4700:4700::1111'],
]);

it('trusts the app origin itself without pinning', function () {
    expect(urlGuard()->assertAllowed('http://127.0.0.1:8000/demo-shop/tecnoshop/ps5-slim'))->toBeNull();
});

it('blocks unsafe urls', function (string $url, array $dns = []) {
    urlGuard($dns)->assertAllowed($url);
})->throws(UnsafeUrl::class)->with([
    'loopback' => ['http://127.0.0.1/'],
    'loopback on another port than the trusted one' => ['http://127.0.0.1:6379/'],
    'private 10/8' => ['http://10.0.0.5/'],
    'private 192.168/16' => ['http://192.168.1.1/admin'],
    'private 172.16/12' => ['http://172.16.0.1/'],
    'cloud metadata (link-local)' => ['http://169.254.169.254/latest/meta-data/'],
    'carrier-grade NAT' => ['http://100.64.0.1/'],
    'unspecified' => ['http://0.0.0.0/'],
    'IPv6 loopback' => ['http://[::1]/'],
    'IPv6 unique local' => ['http://[fd00::1]/'],
    'hostname resolving to a private IP' => ['https://evil.example/', ['evil.example' => ['10.0.0.1']]],
    'hostname with one private record among public ones' => ['https://mixed.example/', ['mixed.example' => ['93.184.216.34', '127.0.0.1']]],
    'decimal-encoded loopback' => ['http://2130706433/', ['2130706433' => ['127.0.0.1']]],
    'localhost' => ['http://localhost/', ['localhost' => ['127.0.0.1']]],
    'unresolvable host' => ['https://nowhere.example/'],
    'file scheme' => ['file:///etc/passwd'],
    'ftp scheme' => ['ftp://shop.example/', ['shop.example' => ['93.184.216.34']]],
    'gopher scheme' => ['gopher://shop.example/', ['shop.example' => ['93.184.216.34']]],
    'credentials in url' => ['https://user:pass@shop.example/', ['shop.example' => ['93.184.216.34']]],
    'non-standard port' => ['https://shop.example:22/', ['shop.example' => ['93.184.216.34']]],
    'not a url' => ['not a url'],
    'empty' => [''],
]);
