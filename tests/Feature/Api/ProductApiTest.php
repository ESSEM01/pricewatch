<?php

use App\Models\Forecast;
use App\Models\Listing;
use App\Models\PricePoint;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12 10:00:00');
    $this->user = User::factory()->create();
});

function apiProduct(User $user): Product
{
    $product = Product::factory()->for($user)->create(['name' => 'Console', 'sku' => 'PS5']);
    Listing::factory()->for($product)->for(Store::factory()->state(['name' => 'Mine']))->own()->create(['current_price' => 49990]);
    Listing::factory()->for($product)->for(Store::factory()->state(['name' => 'Rival']))->create(['current_price' => 45990]);

    return $product;
}

it('requires a token', function () {
    $this->getJson('/api/products')->assertUnauthorized();
});

it('lists the user\'s products with their price position', function () {
    $product = apiProduct($this->user);
    apiProduct(User::factory()->create());
    Sanctum::actingAs($this->user);

    $this->getJson('/api/products')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $product->id)
        ->assertJsonPath('data.0.name', 'Console')
        ->assertJsonPath('data.0.own_price.amount', 49990)
        ->assertJsonPath('data.0.own_price.currency', 'EUR')
        ->assertJsonPath('data.0.cheapest_competitor.store', 'Rival')
        ->assertJsonPath('data.0.cheapest_competitor.price.amount', 45990)
        ->assertJsonStructure(['data', 'links', 'meta']);
});

it('shows one product with its listings', function () {
    $product = apiProduct($this->user);
    Sanctum::actingAs($this->user);

    $this->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data.listings')
        ->assertJsonPath('data.listings.0.store', 'Mine')
        ->assertJsonPath('data.listings.0.is_own', true)
        ->assertJsonPath('data.listings.0.status', 'active')
        ->assertJsonStructure(['data' => ['listings' => [['id', 'url', 'price' => ['amount', 'currency'], 'last_checked_at']]]]);
});

it('hides other users\' products', function (string $path) {
    $foreign = apiProduct(User::factory()->create());
    Sanctum::actingAs($this->user);

    $this->getJson(str_replace('{id}', (string) $foreign->id, $path))->assertForbidden();
})->with(['/api/products/{id}', '/api/products/{id}/history', '/api/products/{id}/forecast']);

it('returns price history for the requested window', function () {
    $product = apiProduct($this->user);
    $listing = $product->listings()->where('is_own', true)->sole();
    PricePoint::factory()->for($listing)->create(['price' => 50990, 'recorded_at' => now()->subDays(40)]);
    PricePoint::factory()->for($listing)->create(['price' => 49990, 'recorded_at' => now()->subDays(5)]);
    Sanctum::actingAs($this->user);

    $this->getJson("/api/products/{$product->id}/history?days=30")
        ->assertOk()
        ->assertJsonPath('data.0.store', 'Mine')
        ->assertJsonCount(1, 'data.0.points')
        ->assertJsonPath('data.0.points.0.price', 49990)
        ->assertJsonPath('data.0.points.0.recorded_at', '2026-11-07T10:00:00+00:00');
});

it('validates the history window', function (string $days) {
    $product = apiProduct($this->user);
    Sanctum::actingAs($this->user);

    $this->getJson("/api/products/{$product->id}/history?days={$days}")->assertUnprocessable()->assertJsonValidationErrors('days');
})->with(['0', '400', 'abc']);

it('returns forecasts per listing', function () {
    $product = apiProduct($this->user);
    $listing = $product->listings()->where('is_own', true)->sole();
    Forecast::factory()->for($listing)->create(['model' => 'holt_winters', 'mape' => 2.5]);
    Sanctum::actingAs($this->user);

    $this->getJson("/api/products/{$product->id}/forecast")
        ->assertOk()
        ->assertJsonPath('data.0.store', 'Mine')
        ->assertJsonPath('data.0.forecast.model', 'holt_winters')
        ->assertJsonPath('data.0.forecast.backtest_mape', 2.5)
        ->assertJsonCount(14, 'data.0.forecast.points')
        ->assertJsonPath('data.1.forecast', null);
});

it('answers 422 when no listing has enough history to forecast', function () {
    $product = apiProduct($this->user);
    Sanctum::actingAs($this->user);

    $this->getJson("/api/products/{$product->id}/forecast")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Not enough price history to forecast yet: at least 14 days are needed.');
});

it('rate limits each user', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/products')->assertOk()->assertHeader('X-RateLimit-Limit', '60');
});
