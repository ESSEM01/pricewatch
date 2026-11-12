<?php

use App\Models\Forecast;
use App\Models\Listing;
use App\Models\PricePoint;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12 10:00:00');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function chartDataFrom(string $html): array
{
    preg_match('#<script type="application/json" data-chart-data>(.*?)</script>#s', $html, $match);

    return json_decode($match[1] ?? 'null', true, flags: JSON_THROW_ON_ERROR);
}

it('embeds history and forecasts for the chart, own store first', function () {
    $product = Product::factory()->for($this->user)->create();
    $competitor = Listing::factory()->for($product)->for(Store::factory()->state(['name' => 'Rival']))->create(['current_price' => 9000]);
    $own = Listing::factory()->for($product)->for(Store::factory()->state(['name' => 'Mine']))->own()->create(['current_price' => 10000]);
    PricePoint::factory()->for($own)->create(['price' => 10000, 'recorded_at' => now()->subDays(3)]);
    Forecast::factory()->for($own)->create(['model' => 'holt_winters', 'mape' => 2.5]);

    $data = chartDataFrom($this->get(route('products.show', $product))->assertOk()->getContent());

    expect($data['series'])->toHaveCount(2)
        ->and($data['series'][0]['name'])->toBe('Mine')
        ->and($data['series'][0]['own'])->toBeTrue()
        ->and($data['series'][0]['history'])->toHaveCount(4) // 3 days ago through today
        ->and($data['series'][0]['history'][0][1])->toEqual(100) // euros; JSON drops the ".0"
        ->and($data['series'][0]['forecast'])->toHaveCount(14)
        ->and($data['series'][1]['name'])->toBe('Rival')
        ->and($data['series'][1]['forecast'])->toBeNull();
});

it('explains the forecast, its accuracy and the price position', function () {
    $product = Product::factory()->for($this->user)->create();
    $own = Listing::factory()->for($product)->for(Store::factory()->state(['name' => 'Mine']))->own()->create(['current_price' => 10600]);
    Listing::factory()->for($product)->for(Store::factory()->state(['name' => 'Rival']))->create(['current_price' => 10000]);
    Forecast::factory()->for($own)->create(['model' => 'holt_winters', 'mape' => 2.5]);

    $this->get(route('products.show', $product))
        ->assertSee('Holt-Winters')
        ->assertSee('2.5%')
        ->assertSee('You are 6.0% above the cheapest competitor (Rival).');
});

it('says when there is not enough history to forecast', function () {
    $product = Product::factory()->for($this->user)->create();
    Listing::factory()->for($product)->create();

    $this->get(route('products.show', $product))->assertSee('Not enough history yet');
});

it('mentions recurring promotions', function () {
    $product = Product::factory()->for($this->user)->create();
    $listing = Listing::factory()->for($product)->for(Store::factory()->state(['name' => 'PromoShop']))->create();
    $start = now()->startOfDay()->subDays(55);
    for ($i = 0; $i < 56; $i++) {
        $date = $start->addDays($i);
        $promo = $date->isoWeekday() >= 5 && intdiv($i, 7) % 2 === 0;
        PricePoint::factory()->for($listing)->create(['price' => $promo ? 8800 : 10000, 'recorded_at' => $date->setTime(9, 0)]);
    }

    $this->get(route('products.show', $product))->assertSee('PromoShop often cuts its price on Friday, Saturday and Sunday.');
});
