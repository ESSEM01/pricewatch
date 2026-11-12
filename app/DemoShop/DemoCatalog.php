<?php

namespace App\DemoShop;

/**
 * The fictional stores and products served under /demo-shop.
 */
final class DemoCatalog
{
    /**
     * @param  list<DemoStore>  $stores
     * @param  list<DemoProduct>  $products
     */
    public function __construct(
        private readonly array $stores,
        private readonly array $products,
    ) {}

    /**
     * @param  array{stores: array<string, array{name: string, strategy: string, markup: float}>, products: array<string, array{name: string, base_price: int}>}  $config
     */
    public static function fromConfig(array $config): self
    {
        $stores = [];
        foreach ($config['stores'] as $slug => $store) {
            $stores[] = new DemoStore($slug, $store['name'], PricingStrategy::from($store['strategy']), $store['markup']);
        }

        $products = [];
        foreach ($config['products'] as $slug => $product) {
            $products[] = new DemoProduct($slug, $product['name'], $product['base_price']);
        }

        return new self($stores, $products);
    }

    /** @return list<DemoStore> */
    public function stores(): array
    {
        return $this->stores;
    }

    /** @return list<DemoProduct> */
    public function products(): array
    {
        return $this->products;
    }

    public function store(string $slug): ?DemoStore
    {
        return array_find($this->stores, fn (DemoStore $store) => $store->slug === $slug);
    }

    public function product(string $slug): ?DemoProduct
    {
        return array_find($this->products, fn (DemoProduct $product) => $product->slug === $slug);
    }
}
