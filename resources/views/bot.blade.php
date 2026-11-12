<x-layouts::public :title="__('PriceWatchBot')">
    <article class="max-w-2xl pt-10 sm:pt-16">
        <h1 class="text-3xl font-semibold tracking-tight">PriceWatchBot</h1>
        <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">
            {{ __('If you found this page in your server logs: PriceWatchBot reads the price of product pages that a PriceWatch user asked to follow.') }}
        </p>

        <h2 class="mt-10 text-lg font-semibold">{{ __('What it does') }}</h2>
        <ul class="mt-3 list-disc space-y-2 ps-5 text-zinc-700 dark:text-zinc-300">
            <li>{{ __('Fetches only the product pages users added, usually every six hours.') }}</li>
            <li>{{ __('Sends at most a few requests per minute to the same store, and backs off for hours after repeated errors.') }}</li>
            <li>{{ __('Reads the price from the page; it does not log in, add to cart or submit forms.') }}</li>
            <li>{{ __('Identifies itself with the user agent') }} <code class="text-sm">{{ config('scraping.user_agent') }}</code>.</li>
        </ul>

        <h2 class="mt-10 text-lg font-semibold">{{ __('How to stop it') }}</h2>
        <p class="mt-3 text-zinc-700 dark:text-zinc-300">
            {{ __('PriceWatchBot follows robots.txt (RFC 9309). To keep it out of your whole site, add:') }}
        </p>
        <pre class="mt-3 overflow-x-auto rounded-md bg-zinc-100 p-4 text-sm dark:bg-zinc-800"><code>User-agent: PriceWatchBot
Disallow: /</code></pre>
        <p class="mt-3 text-zinc-700 dark:text-zinc-300">
            {{ __('robots.txt is re-read at least once a day. Questions or problems:') }}
            <a href="{{ config('pricewatch.repository_url') }}/issues" class="underline underline-offset-2">{{ __('open an issue') }}</a>.
        </p>
    </article>
</x-layouts::public>
