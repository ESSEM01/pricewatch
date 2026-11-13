<?php

it('sends the security headers on pages and api responses', function (string $uri) {
    $this->get($uri)
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
})->with(['/', '/up', '/api/products']);

it('does not restrict scripts or styles, which Livewire and Vite need', function () {
    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)->not->toContain('script-src')->not->toContain('default-src')->not->toContain('style-src');
});

it('sends HSTS only in production over https', function (string $environment, string $url, bool $expected) {
    $this->app['env'] = $environment;

    $response = $this->get($url);

    $expected
        ? $response->assertHeader('Strict-Transport-Security', 'max-age=31536000')
        : $response->assertHeaderMissing('Strict-Transport-Security');
})->with([
    'production, https' => ['production', 'https://localhost/up', true],
    'production, http' => ['production', 'http://localhost/up', false],
    'local, https' => ['local', 'https://localhost/up', false],
]);
