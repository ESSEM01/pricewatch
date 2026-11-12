<?php

/*
|--------------------------------------------------------------------------
| Demo Shop
|--------------------------------------------------------------------------
|
| Fictional stores served by the app itself under /demo-shop. They give the
| scraper real pages to read, with prices that move over time, without
| depending on (or hammering) third-party sites. Prices are in cents.
|
*/

return [

    'enabled' => env('DEMO_SHOP_ENABLED', true),

    // "Try the demo" button on the home page: signs in as the seeded demo user.
    'demo_login' => env('DEMO_LOGIN', true),

    // Rebuild the demo account every night (for a public demo server).
    'nightly_reset' => env('DEMO_NIGHTLY_RESET', false),

    // Day zero for the time-based pricing strategies.
    'anchor_date' => '2026-01-01',

    'stores' => [
        'tecnoshop' => ['name' => 'TecnoShop', 'strategy' => 'weekend_promo', 'markup' => 1.00],
        // Markups chosen so the demo user's store (TecnoShop) is sometimes the
        // cheapest and sometimes undercut, depending on the day.
        'elettrocasa' => ['name' => 'ElettroCasa', 'strategy' => 'trend_down', 'markup' => 1.12],
        'prezzopazzo' => ['name' => 'PrezzoPazzo', 'strategy' => 'volatile', 'markup' => 1.02],
    ],

    'products' => [
        'iphone-16-128' => ['name' => 'Apple iPhone 16 128GB', 'base_price' => 97_900],
        'galaxy-s25-256' => ['name' => 'Samsung Galaxy S25 256GB', 'base_price' => 89_900],
        'airpods-pro-2' => ['name' => 'Apple AirPods Pro 2', 'base_price' => 27_900],
        'ps5-slim' => ['name' => 'Sony PlayStation 5 Slim', 'base_price' => 54_999],
        'dyson-v15' => ['name' => 'Dyson V15 Detect', 'base_price' => 69_900],
        'kindle-paperwhite' => ['name' => 'Kindle Paperwhite 16GB', 'base_price' => 16_999],
        'macbook-air-13-m4' => ['name' => 'Apple MacBook Air 13" M4', 'base_price' => 124_900],
        'switch-2' => ['name' => 'Nintendo Switch 2', 'base_price' => 46_999],
        'forerunner-265' => ['name' => 'Garmin Forerunner 265', 'base_price' => 44_999],
        'lg-oled-c4-55' => ['name' => 'LG OLED evo C4 55"', 'base_price' => 149_900],
    ],

];
