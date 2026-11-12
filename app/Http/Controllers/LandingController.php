<?php

namespace App\Http\Controllers;

use App\DemoShop\DemoCatalog;
use App\DemoShop\DemoProduct;
use App\DemoShop\DemoStore;
use App\DemoShop\PriceSimulator;
use Illuminate\Contracts\View\View;

/**
 * The public home page. Its centrepiece is real data: today's prices from
 * the built-in demo shop, the same pages the scraper reads.
 */
class LandingController extends Controller
{
    public function __invoke(DemoCatalog $catalog, PriceSimulator $simulator): View
    {
        $stores = $catalog->stores();

        $board = array_map(function (DemoProduct $product) use ($stores, $simulator): array {
            $prices = array_map(fn (DemoStore $store): int => $simulator->priceFor($store, $product, now()), $stores);

            // No stores configured means no prices, not a crash on the home page.
            return ['product' => $product, 'prices' => $prices, 'cheapest' => $prices === [] ? null : min($prices)];
        }, array_slice($catalog->products(), 0, 5));

        return view('welcome', ['stores' => $stores, 'board' => $board]);
    }
}
