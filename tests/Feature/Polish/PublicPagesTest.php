<?php

use App\DemoShop\DemoCatalog;
use App\DemoShop\PriceSimulator;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12 10:00:00');
});

it('presents the product with today\'s real demo prices', function () {
    $catalog = app(DemoCatalog::class);
    $product = $catalog->products()[0];
    $store = $catalog->stores()[0];
    $price = app(PriceSimulator::class)->priceFor($store, $product, now());

    $this->get('/')
        ->assertOk()
        ->assertSee('PriceWatch')
        ->assertSee($product->name)
        ->assertSee(number_format($price / 100, 2, ',', '.').' €');
});

it('explains the bot that the user agent links to', function () {
    expect(config('scraping.user_agent'))->toContain('/bot');

    $this->get('/bot')
        ->assertOk()
        ->assertSee('PriceWatchBot')
        ->assertSee('User-agent: PriceWatchBot', false)
        ->assertSee('robots.txt');
});

it('signs visitors into the demo account in one click', function () {
    config(['demo_shop.demo_login' => true]);
    $this->seed(DemoDataSeeder::class);

    $this->post(route('demo.login'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs(User::query()->where('email', DemoDataSeeder::EMAIL)->sole());
});

it('offers no demo login when it is turned off or not seeded', function (bool $enabled, bool $seeded) {
    config(['demo_shop.demo_login' => $enabled]);
    if ($seeded) {
        $this->seed(DemoDataSeeder::class);
    }

    $this->post(route('demo.login'))->assertNotFound();
    $this->assertGuest();
})->with([
    'disabled' => [false, true],
    'not seeded' => [true, false],
]);

it('resets the demo account on demand and nightly', function () {
    $this->seed(DemoDataSeeder::class);
    Product::query()->first()?->delete();

    $this->artisan('demo:reset')->assertSuccessful();

    expect(Product::query()->count())->toBe(count(app(DemoCatalog::class)->products()))
        ->and(collect(app(Schedule::class)->events())->contains(fn ($e) => str_contains((string) $e->command, 'demo:reset')))->toBeTrue();
});
