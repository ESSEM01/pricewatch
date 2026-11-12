@php use App\Support\Money; @endphp

<x-layouts::public :title="__('Competitor price tracking')">
    <section class="pt-10 sm:pt-20">
        <div class="max-w-2xl">
            <h1 class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                {{ __('Know when a competitor moves its price.') }}
            </h1>
            <p class="mt-5 max-w-xl text-lg text-zinc-600 dark:text-zinc-400">
                {{ __('PriceWatch reads product pages the way search engines do, keeps every change, and estimates where each price is heading over the next two weeks.') }}
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-4">
                @if (config('demo_shop.demo_login'))
                    <form method="POST" action="{{ route('demo.login') }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2a78d6] dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                            {{ __('Try the demo') }}
                        </button>
                    </form>
                @endif
                <a href="{{ route('register') }}" class="text-sm font-medium underline underline-offset-4">{{ __('Create an account') }}</a>
            </div>
        </div>
    </section>

    {{-- The product's own world: real prices, read from the built-in demo shop today. --}}
    <section class="mt-16 sm:mt-24" aria-labelledby="board-heading">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
            <h2 id="board-heading" class="text-base font-semibold">{{ __('Today on the demo shop') }}</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ now()->format('D j M Y') }} ·
                <a href="{{ route('demo-shop.index') }}" class="underline underline-offset-2">{{ __('the pages the scraper reads') }}</a>
            </p>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[34rem] text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-left text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        <th scope="col" class="py-2 pe-4 font-normal">{{ __('Product') }}</th>
                        @foreach ($stores as $store)
                            <th scope="col" class="py-2 ps-4 text-right font-normal">{{ $store->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($board as $row)
                        <tr class="border-b border-zinc-100 dark:border-zinc-800">
                            <th scope="row" class="py-3 pe-4 text-left font-medium">{{ $row['product']->name }}</th>
                            @foreach ($row['prices'] as $price)
                                <td class="py-3 ps-4 text-right tabular-nums">
                                    @if ($price === $row['cheapest'])
                                        <span class="font-semibold text-[#1c5cab] dark:text-[#86b6ef]">{{ Money::format($price) }}</span>
                                        <span class="sr-only">({{ __('lowest') }})</span>
                                    @else
                                        <span class="text-zinc-600 dark:text-zinc-400">{{ Money::format($price) }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Lowest price in each row in blue. Tomorrow these numbers will be different.') }}</p>
    </section>

    <section class="mt-20 grid gap-12 sm:mt-28 sm:grid-cols-[1fr_1.4fr]">
        <h2 class="text-2xl font-semibold tracking-tight">{{ __('How a price is read') }}</h2>
        <div class="text-zinc-700 dark:text-zinc-300">
            <ol class="list-decimal space-y-3 ps-5">
                <li>{{ __('The structured product data shops publish for Google Shopping (schema.org JSON-LD).') }}</li>
                <li>{{ __('If there is none, the OpenGraph price tags.') }}</li>
                <li>{{ __('As a last resort, a CSS selector you set for that store.') }}</li>
            </ol>
            <p class="mt-6">
                {{ __('The bot honours robots.txt, sends a few requests per minute per store, and refuses any link that points inside a private network.') }}
                <a href="{{ route('bot') }}" class="underline underline-offset-2">{{ __('More about the bot') }}</a>
            </p>
        </div>
    </section>

    <section class="mt-20 max-w-2xl sm:mt-28 sm:ms-auto">
        <h2 class="text-2xl font-semibold tracking-tight">{{ __('A forecast that shows its error') }}</h2>
        <p class="mt-4 text-zinc-700 dark:text-zinc-300">
            {{ __('For every store, three models (moving average, linear trend, Holt-Winters with a weekly season) are tested on the last four weeks as if they were the future. The one that was least wrong makes the 14-day forecast, and its real error is printed next to it, together with a range that widens the further ahead it looks.') }}
        </p>
    </section>
</x-layouts::public>
