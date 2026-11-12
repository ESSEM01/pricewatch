<?php

namespace App\Scraping;

use App\DemoShop\DemoCatalog;
use App\Models\Store;
use App\Scraping\Http\UrlGuard;

/**
 * Finds (or creates) the store a product URL belongs to.
 */
final class StoreResolver
{
    public function __construct(private readonly DemoCatalog $catalog) {}

    public function resolve(string $url): Store
    {
        // All demo stores live on the app's own host, so tell them apart by path.
        $demo = $this->demoStoreSlug($url);

        if ($demo !== null) {
            return Store::query()->firstOrCreate(['domain' => "{$demo['slug']}.demo-shop"], ['name' => $demo['name']]);
        }

        $domain = (string) preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));

        return Store::query()->firstOrCreate(['domain' => $domain], ['name' => $domain]);
    }

    /**
     * @return array{slug: string, name: string}|null
     */
    private function demoStoreSlug(string $url): ?array
    {
        if (UrlGuard::origin($url) !== UrlGuard::origin((string) config('app.url'))) {
            return null;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        if (preg_match('#^/demo-shop/([^/]+)/#', $path, $match) !== 1) {
            return null;
        }

        $store = $this->catalog->store($match[1]);

        return $store === null ? null : ['slug' => $store->slug, 'name' => $store->name];
    }
}
