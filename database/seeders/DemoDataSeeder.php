<?php

namespace Database\Seeders;

use App\DemoShop\DemoCatalog;
use App\DemoShop\PriceSimulator;
use App\Models\Listing;
use App\Models\PricePoint;
use App\Models\User;
use App\Scraping\StoreResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A ready-to-show account: every demo-shop product on every demo store,
 * with half a year of simulated daily prices. Safe to run repeatedly: it
 * rebuilds the demo user's data from scratch.
 */
class DemoDataSeeder extends Seeder
{
    public const EMAIL = 'demo@pricewatch.test';

    public const HISTORY_DAYS = 180;

    /** Prices are "observed" once a day at this hour. */
    private const CHECK_HOUR = 9;

    public function run(DemoCatalog $catalog, PriceSimulator $simulator, StoreResolver $stores): void
    {
        $user = User::query()->updateOrCreate(['email' => self::EMAIL], [
            'name' => 'Demo',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $user->products()->delete(); // cascades to listings and price points

        $today = now()->startOfDay()->setTime(self::CHECK_HOUR, 0);

        foreach ($catalog->products() as $demoProduct) {
            $product = $user->products()->create([
                'name' => $demoProduct->name,
                'sku' => strtoupper($demoProduct->slug),
            ]);

            foreach ($catalog->stores() as $index => $demoStore) {
                $url = route('demo-shop.product', [$demoStore->slug, $demoProduct->slug]);

                /** @var Listing $listing */
                $listing = $product->listings()->create([
                    'url' => $url,
                    'store_id' => $stores->resolve($url)->id,
                    'is_own' => $index === 0, // the user "is" the first store
                ]);

                $rows = [];
                $price = 0;

                for ($daysAgo = self::HISTORY_DAYS - 1; $daysAgo >= 0; $daysAgo--) {
                    $day = $today->subDays($daysAgo);
                    $price = $simulator->priceFor($demoStore, $demoProduct, $day);
                    $rows[] = ['listing_id' => $listing->id, 'price' => $price, 'currency' => 'EUR', 'recorded_at' => $day];
                }

                PricePoint::query()->insert($rows);

                $listing->forceFill([
                    'current_price' => $price,
                    'last_checked_at' => $today,
                    'next_check_at' => null, // due now: the scheduler picks it up
                ])->save();
            }
        }
    }
}
