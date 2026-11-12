<?php

namespace App\Rules;

use App\Scraping\Exceptions\UnsafeUrl;
use App\Scraping\Http\UrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects URLs the scraper would refuse to fetch, so users get the error
 * when they add a link rather than as a silent failed check later.
 */
final class SafeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            app(UrlGuard::class)->assertAllowed(is_string($value) ? $value : '');
        } catch (UnsafeUrl) {
            $fail('The :attribute must be a public http or https web page.');
        }
    }
}
