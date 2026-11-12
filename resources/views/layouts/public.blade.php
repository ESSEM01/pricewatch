@props(['title' => null])

<!DOCTYPE html>
<html lang="en">
    <head>
        @include('partials.head', ['title' => $title])
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <header class="mx-auto flex max-w-5xl items-center justify-between px-4 py-5 sm:px-6">
            <a href="{{ route('home') }}" class="text-lg font-semibold tracking-tight">PriceWatch</a>
            <nav class="flex items-center gap-5 text-sm" aria-label="{{ __('Main') }}">
                <a href="{{ config('pricewatch.repository_url') }}" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">GitHub</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="font-medium">{{ __('Open the app') }}</a>
                @else
                    <a href="{{ route('login') }}" class="font-medium">{{ __('Log in') }}</a>
                @endauth
            </nav>
        </header>

        <main class="mx-auto max-w-5xl px-4 sm:px-6">
            {{ $slot }}
        </main>

        <footer class="mx-auto mt-24 max-w-5xl border-t border-zinc-200 px-4 py-8 text-sm text-zinc-500 sm:px-6 dark:border-zinc-800 dark:text-zinc-400">
            {{ __('A portfolio project built with Laravel 13, Livewire 4 and Pest.') }}
            <a href="{{ config('pricewatch.repository_url') }}" class="underline underline-offset-2">{{ __('Source code') }}</a>
            · <a href="{{ route('bot') }}" class="underline underline-offset-2">{{ __('About the bot') }}</a>
        </footer>

        @fluxScripts
    </body>
</html>
