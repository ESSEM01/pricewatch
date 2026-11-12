<?php

use App\Scraping\Http\RobotsTxt;

function robotsAllows(string $robots, string $path, string $agent = 'PriceWatchBot'): bool
{
    return RobotsTxt::parse($robots)->allows($agent, $path);
}

it('allows everything when there are no rules', function (string $robots) {
    expect(robotsAllows($robots, '/anything'))->toBeTrue();
})->with(['', "User-agent: *\nDisallow:", '# only a comment']);

it('applies the wildcard group', function () {
    $robots = "User-agent: *\nDisallow: /cart";

    expect(robotsAllows($robots, '/cart/1'))->toBeFalse()
        ->and(robotsAllows($robots, '/product/1'))->toBeTrue();
});

it('prefers the group naming our bot over the wildcard', function () {
    $robots = "User-agent: *\nDisallow: /\n\nUser-agent: PriceWatchBot\nAllow: /\n";

    expect(robotsAllows($robots, '/product/1'))->toBeTrue()
        ->and(robotsAllows($robots, '/product/1', 'OtherBot'))->toBeFalse();
});

it('shares rules between consecutive user-agent lines', function () {
    $robots = "User-agent: SomeBot\nUser-agent: pricewatchbot\nDisallow: /private";

    expect(robotsAllows($robots, '/private/x'))->toBeFalse();
});

it('lets the longest matching rule win, allow on ties', function () {
    $robots = "User-agent: *\nDisallow: /shop\nAllow: /shop/products\nDisallow: /same\nAllow: /same";

    expect(robotsAllows($robots, '/shop/products/1'))->toBeTrue()
        ->and(robotsAllows($robots, '/shop/cart'))->toBeFalse()
        ->and(robotsAllows($robots, '/same'))->toBeTrue();
});

it('supports * wildcards and $ anchors', function () {
    $robots = "User-agent: *\nDisallow: /*.pdf$\nDisallow: /*?sort=";

    expect(robotsAllows($robots, '/docs/manual.pdf'))->toBeFalse()
        ->and(robotsAllows($robots, '/docs/manual.pdf?download=1'))->toBeTrue()
        ->and(robotsAllows($robots, '/list?sort=price'))->toBeFalse()
        ->and(robotsAllows($robots, '/list?page=2'))->toBeTrue();
});

it('tolerates comments, CRLF line endings and odd casing', function () {
    $robots = "USER-AGENT: *   # everyone\r\nDISALLOW: /secret # keep out\r\n";

    expect(robotsAllows($robots, '/secret'))->toBeFalse();
});

it('can disallow everything', function () {
    expect(RobotsTxt::disallowAll()->allows('PriceWatchBot', '/'))->toBeFalse();
});
