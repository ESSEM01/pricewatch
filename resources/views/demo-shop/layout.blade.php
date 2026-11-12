<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · Demo Shop</title>
    @stack('head')
    {{-- Deliberately plain and self-contained: it should look like "some other shop", not like PriceWatch. --}}
    <style>
        :root { color-scheme: light; --ink: #1d1d1f; --muted: #6e6e73; --line: #e5e5ea; --accent: #b4232a; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 16px/1.5 Georgia, 'Times New Roman', serif; color: var(--ink); background: #fbfaf7; }
        header { border-bottom: 1px solid var(--line); padding: 16px; }
        header a { color: inherit; text-decoration: none; font-weight: bold; letter-spacing: .02em; }
        main { max-width: 760px; margin: 0 auto; padding: 24px 16px 48px; }
        .notice { font: 13px/1.4 system-ui, sans-serif; color: var(--muted); background: #f1efe8; padding: 8px 12px; border-radius: 4px; }
        h1 { font-size: 28px; line-height: 1.2; margin: 24px 0 8px; }
        .price { display: inline-block; font: 600 32px/1 system-ui, sans-serif; color: var(--accent); margin: 16px 0; }
        table { width: 100%; border-collapse: collapse; font: 15px/1.4 system-ui, sans-serif; }
        th, td { text-align: left; padding: 8px 4px; border-bottom: 1px solid var(--line); }
        td a { color: var(--accent); }
    </style>
</head>
<body>
    <header><a href="{{ route('demo-shop.index') }}">Demo Shop</a></header>
    <main>
        <p class="notice">Negozi fittizi usati da PriceWatch per dimostrare lo scraping. Prezzi generati, nessun prodotto in vendita.</p>
        @yield('content')
    </main>
</body>
</html>
