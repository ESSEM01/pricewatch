<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser hardening headers on every response. The CSP only forbids framing:
 * a script/style policy would break Livewire and Vite without nonces.
 */
final class SecurityHeaders
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        // Clickjacking: X-Frame-Options for old browsers, frame-ancestors for the rest.
        'X-Frame-Options' => 'DENY',
        'Content-Security-Policy' => "frame-ancestors 'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        // Only over HTTPS (browsers ignore it otherwise) and only in production,
        // so a local https setup does not pin the browser to https for a year.
        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
