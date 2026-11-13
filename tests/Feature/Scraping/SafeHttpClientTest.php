<?php

use App\Scraping\Exceptions\UnsafeUrl;
use App\Scraping\Http\SafeHttpClient;
use App\Scraping\Http\UrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->lookups = [];

    // Fake DNS that remembers every lookup, so each redirect hop can be shown to be vetted.
    $this->app->instance(UrlGuard::class, new UrlGuard(
        trustedOrigins: [],
        resolve: function (string $host): array {
            $this->lookups[] = $host;

            return [
                'shop.example' => ['93.184.216.34'],
                'cdn.example' => ['93.184.216.35'],
                'evil.example' => ['10.0.0.1'],
            ][$host] ?? [];
        },
    ));
});

/**
 * Serves the given URL => response map and records the curl options of every request.
 *
 * @param  array<string, mixed>  $routes
 * @return ArrayObject<int, array{url: string, options: array<string, mixed>}>
 */
function fakeHops(array $routes): ArrayObject
{
    $sent = new ArrayObject;

    Http::fake(function (Request $request, array $options) use ($routes, $sent) {
        $sent[] = ['url' => $request->url(), 'options' => $options];

        return $routes[$request->url()] ?? Http::response('not found', 404);
    });

    return $sent;
}

it('follows redirects itself, vetting and pinning every hop', function () {
    $sent = fakeHops([
        'https://shop.example/p/1' => Http::response('', 301, ['Location' => 'https://cdn.example/p/1']),
        'https://cdn.example/p/1' => Http::response('', 302, ['Location' => '/final?x=1']),
        'https://cdn.example/final?x=1' => Http::response('<html>ok</html>'),
    ]);

    $response = app(SafeHttpClient::class)->get('https://shop.example/p/1');

    expect($response->body())->toBe('<html>ok</html>')
        ->and($this->lookups)->toBe(['shop.example', 'cdn.example', 'cdn.example'])
        ->and(array_column($sent->getArrayCopy(), 'url'))->toBe([
            'https://shop.example/p/1',
            'https://cdn.example/p/1',
            'https://cdn.example/final?x=1', // relative Location resolved against the current hop
        ]);

    foreach ($sent as $hop) {
        // Guzzle must never follow a redirect on its own: it would re-resolve DNS unpinned.
        expect($hop['options']['allow_redirects'])->toBeFalse();
    }

    expect($sent[0]['options']['curl'][CURLOPT_RESOLVE])->toBe(['shop.example:443:93.184.216.34'])
        ->and($sent[1]['options']['curl'][CURLOPT_RESOLVE])->toBe(['cdn.example:443:93.184.216.35'])
        ->and($sent[2]['options']['curl'][CURLOPT_RESOLVE])->toBe(['cdn.example:443:93.184.216.35']);
});

it('pins the port of a redirect hop, not the one of the first url', function () {
    $sent = fakeHops([
        'https://shop.example/p/1' => Http::response('', 302, ['Location' => 'http://cdn.example/p/1']),
        'http://cdn.example/p/1' => Http::response('ok'),
    ]);

    app(SafeHttpClient::class)->get('https://shop.example/p/1');

    expect($sent[1]['options']['curl'][CURLOPT_RESOLVE])->toBe(['cdn.example:80:93.184.216.35']);
});

it('blocks a redirect to a private address before sending anything there', function (string $location) {
    $sent = fakeHops([
        'https://shop.example/p/1' => Http::response('', 302, ['Location' => $location]),
    ]);

    expect(fn () => app(SafeHttpClient::class)->get('https://shop.example/p/1'))
        ->toThrow(UnsafeUrl::class, 'non-public');

    expect($sent)->toHaveCount(1);
})->with([
    'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
    'hostname resolving inside the network' => ['https://evil.example/admin'],
    'protocol-relative' => ['//10.0.0.1/'],
]);

it('blocks a redirect to another scheme or port', function (string $location) {
    $sent = fakeHops([
        'https://shop.example/p/1' => Http::response('', 302, ['Location' => $location]),
    ]);

    expect(fn () => app(SafeHttpClient::class)->get('https://shop.example/p/1'))->toThrow(UnsafeUrl::class);
    expect($sent)->toHaveCount(1);
})->with([
    'ftp' => ['ftp://shop.example/file'],
    'file' => ['file:///etc/passwd'],
    'redis port' => ['http://shop.example:6379/'],
]);

it('gives up after the configured number of redirects', function () {
    config(['scraping.max_redirects' => 2]);
    $sent = fakeHops([
        'https://shop.example/a' => Http::response('', 302, ['Location' => '/b']),
        'https://shop.example/b' => Http::response('', 302, ['Location' => '/c']),
        'https://shop.example/c' => Http::response('', 302, ['Location' => '/d']),
        'https://shop.example/d' => Http::response('never reached'),
    ]);

    expect(fn () => app(SafeHttpClient::class)->get('https://shop.example/a'))
        ->toThrow(ConnectionException::class, 'Too many redirects');

    expect($sent)->toHaveCount(3);
});

it('returns a redirect without a location as it is', function () {
    fakeHops(['https://shop.example/p/1' => Http::response('', 304)]);

    expect(app(SafeHttpClient::class)->get('https://shop.example/p/1')->status())->toBe(304);
});
