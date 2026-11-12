<?php

use App\Scraping\Exceptions\ScrapeFailed;
use App\Scraping\Http\PageFetcher;
use App\Scraping\Http\UrlGuard;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Fake DNS: shop.example is public, evil.example points inside the network.
    $this->app->instance(UrlGuard::class, new UrlGuard(
        trustedOrigins: [],
        resolve: fn (string $host): array => [
            'shop.example' => ['93.184.216.34'],
            'other.example' => ['93.184.216.35'],
            'evil.example' => ['10.0.0.1'],
        ][$host] ?? [],
    ));
});

function fetchPage(string $url): string
{
    return app(PageFetcher::class)->fetch($url);
}

function failureOf(callable $callback): ScrapeFailed
{
    try {
        $callback();
    } catch (ScrapeFailed $e) {
        return $e;
    }

    throw new RuntimeException('Expected ScrapeFailed, nothing was thrown.');
}

it('returns the page body and identifies itself', function () {
    Http::fake([
        'shop.example/robots.txt' => Http::response("User-agent: *\nDisallow: /cart"),
        'shop.example/p/1' => Http::response('<html>ok</html>'),
    ]);

    expect(fetchPage('https://shop.example/p/1'))->toBe('<html>ok</html>');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://shop.example/p/1'
        && str_starts_with($request->header('User-Agent')[0] ?? '', 'PriceWatchBot/'));
});

it('reads robots.txt once per site', function () {
    Http::fake([
        'shop.example/robots.txt' => Http::response(''),
        'shop.example/*' => Http::response('<html></html>'),
    ]);

    fetchPage('https://shop.example/p/1');
    fetchPage('https://shop.example/p/2');

    Http::assertSentCount(3);
});

it('refuses pages that robots.txt disallows, permanently', function () {
    Http::fake(['shop.example/robots.txt' => Http::response("User-agent: *\nDisallow: /cart")]);

    $failure = failureOf(fn () => fetchPage('https://shop.example/cart/1'));

    expect($failure->permanent)->toBeTrue()
        ->and($failure->getMessage())->toContain('robots.txt');
    Http::assertSentCount(1);
});

it('treats a missing robots.txt as allow-all and a broken one as disallow-all', function (int $status, bool $allowed) {
    Http::fake([
        'shop.example/robots.txt' => Http::response('', $status),
        'shop.example/p/1' => Http::response('<html></html>'),
    ]);

    $allowed
        ? expect(fetchPage('https://shop.example/p/1'))->toBe('<html></html>')
        : expect(failureOf(fn () => fetchPage('https://shop.example/p/1'))->getMessage())->toContain('robots.txt');
})->with([
    'not found' => [404, true],
    'server error' => [503, false],
]);

it('never sends a request to an unsafe url', function () {
    Http::fake();

    $failure = failureOf(fn () => fetchPage('https://evil.example/'));

    expect($failure->permanent)->toBeTrue();
    Http::assertNothingSent();
});

it('stops a redirect that leads inside the network', function () {
    Http::fake([
        'shop.example/robots.txt' => Http::response(''),
        'shop.example/p/1' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
        '169.254.169.254/*' => Http::response('secret'),
    ]);

    $failure = failureOf(fn () => fetchPage('https://shop.example/p/1'));

    expect($failure->permanent)->toBeTrue()
        ->and($failure->getMessage())->toContain('169.254.169.254')->toContain('non-public');
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '169.254.169.254'));
});

it('reports http errors, permanent only when the product is gone', function (int $status, bool $permanent) {
    Http::fake([
        'shop.example/robots.txt' => Http::response(''),
        'shop.example/p/1' => Http::response('', $status),
    ]);

    $failure = failureOf(fn () => fetchPage('https://shop.example/p/1'));

    expect($failure->permanent)->toBe($permanent)
        ->and($failure->getMessage())->toContain((string) $status);
})->with([
    'not found' => [404, true],
    'gone' => [410, true],
    'rate limited' => [429, false],
    'server error' => [500, false],
]);

it('reports connection failures as temporary', function () {
    Http::fake([
        'shop.example/robots.txt' => Http::response(''),
        'shop.example/p/1' => Http::failedConnection(),
    ]);

    expect(failureOf(fn () => fetchPage('https://shop.example/p/1'))->permanent)->toBeFalse();
});

it('gives up on redirect loops', function () {
    Http::fake([
        'shop.example/robots.txt' => Http::response(''),
        'shop.example/loop' => Http::response('', 302, ['Location' => 'https://shop.example/loop']),
    ]);

    expect(failureOf(fn () => fetchPage('https://shop.example/loop')))->toBeInstanceOf(ScrapeFailed::class);
});

it('refuses oversized pages', function () {
    config(['scraping.max_body_bytes' => 10]);
    Http::fake([
        'shop.example/robots.txt' => Http::response(''),
        'shop.example/p/1' => Http::response(str_repeat('x', 11)),
    ]);

    expect(failureOf(fn () => fetchPage('https://shop.example/p/1'))->getMessage())->toContain('too large');
});
