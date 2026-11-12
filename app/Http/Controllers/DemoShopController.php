<?php

namespace App\Http\Controllers;

use App\DemoShop\DemoCatalog;
use App\DemoShop\PriceSimulator;
use Illuminate\Http\Response;

/**
 * Serves fictional product pages that look, to a scraper, like a real shop:
 * schema.org JSON-LD, OpenGraph price tags and a visible formatted price.
 */
class DemoShopController extends Controller
{
    private const NO_INDEX = ['X-Robots-Tag' => 'noindex, nofollow'];

    public function __construct(
        private readonly DemoCatalog $catalog,
        private readonly PriceSimulator $simulator,
    ) {}

    public function index(): Response
    {
        return response()
            ->view('demo-shop.index', [
                'stores' => $this->catalog->stores(),
                'products' => $this->catalog->products(),
            ])
            ->withHeaders(self::NO_INDEX);
    }

    public function show(string $store, string $product): Response
    {
        $demoStore = $this->catalog->store($store) ?? abort(404);
        $demoProduct = $this->catalog->product($product) ?? abort(404);

        $cents = $this->simulator->priceFor($demoStore, $demoProduct, now());
        $amount = number_format($cents / 100, 2, '.', '');

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $demoProduct->name,
            'sku' => strtoupper("{$demoStore->slug}-{$demoProduct->slug}"),
            'offers' => [
                '@type' => 'Offer',
                'price' => $amount,
                'priceCurrency' => 'EUR',
                'availability' => 'https://schema.org/InStock',
                'url' => route('demo-shop.product', [$demoStore->slug, $demoProduct->slug]),
            ],
        ];

        return response()
            ->view('demo-shop.product', [
                'store' => $demoStore,
                'product' => $demoProduct,
                'amount' => $amount,
                'formatted' => number_format($cents / 100, 2, ',', '.').' €',
                // JSON_HEX_TAG keeps "</script>" in data from closing the tag.
                'jsonLd' => json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR),
            ])
            ->withHeaders(self::NO_INDEX);
    }
}
