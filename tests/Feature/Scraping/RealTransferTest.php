<?php

use App\Scraping\Exceptions\ResponseTooLarge;
use App\Scraping\Exceptions\ScrapeFailed;
use App\Scraping\Http\PageFetcher;
use App\Scraping\Http\SafeHttpClient;
use App\Scraping\Http\UrlGuard;
use Symfony\Component\Process\Process;

/*
 * These tests talk to a real `php -S` server, because what matters here is
 * what curl does (gzip decoding, aborting a transfer), and Http::fake does
 * not go through curl at all.
 */

/**
 * Starts the server on first use and returns its origin; it stops with the process.
 */
function realServer(): string
{
    static $origin = null, $process = null;

    if ($origin === null) {
        // Ask the OS for a free port, then hand it to the server.
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        $process = new Process([PHP_BINARY, '-S', $address, __DIR__.'/../../Fixtures/http/router.php']);
        $process->start();
        $origin = 'http://'.$address;

        for ($tries = 0; $tries < 100 && ! @fsockopen('127.0.0.1', (int) parse_url($origin, PHP_URL_PORT)); $tries++) {
            usleep(50_000);
        }
    }

    return $origin;
}

beforeEach(function () {
    $this->app->instance(UrlGuard::class, new UrlGuard([realServer()]));
    config(['scraping.max_body_bytes' => 100_000]);
});

it('decompresses a gzip page within the limit', function () {
    expect(app(PageFetcher::class)->fetch(realServer().'/small-gzip'))->toBe('<html>small</html>');
});

it('stops a gzip bomb once the decompressed body passes the limit', function () {
    // About 50 KB on the wire, well under the limit; 50 MB once curl decodes it.
    try {
        app(SafeHttpClient::class)->get(realServer().'/gzip-bomb', 1_000_000);
        $this->fail('Expected ResponseTooLarge.');
    } catch (ResponseTooLarge $e) {
        // Stopped at the first decoded chunk past the limit, not after 50 MB.
        expect($e->bytes)->toBeGreaterThan(1_000_000)->toBeLessThan(1_100_000);
    }
});

it('turns an oversized error page into a scrape failure', function () {
    try {
        app(PageFetcher::class)->fetch(realServer().'/error-large');
        $this->fail('Expected ScrapeFailed.');
    } catch (ScrapeFailed $e) {
        expect($e->getMessage())->toContain('too large')
            ->and($e->permanent)->toBeFalse();
    }
});
