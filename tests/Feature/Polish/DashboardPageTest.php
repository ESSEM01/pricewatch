<?php

use App\Enums\ListingStatus;
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

function undercutProduct(User $user, string $name, int $own, int $rival): Product
{
    $product = Product::factory()->for($user)->create(['name' => $name]);
    Listing::factory()->for($product)->for(Store::factory()->state(['name' => 'Mine']))->own()->create(['current_price' => $own]);
    Listing::factory()->for($product)->for(Store::factory()->state(['name' => "Rival of {$name}"]))->create(['current_price' => $rival]);

    return $product;
}

it('summarises the account in plain numbers', function () {
    undercutProduct($this->user, 'Console', 50000, 45000);
    $failing = Listing::factory()->for(Product::factory()->for($this->user))->failing()->create();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Products', '2', 'Store links', '3'])
        ->assertSee('1 failing link');
});

it('lists products where a competitor is cheaper, largest gap first', function () {
    undercutProduct($this->user, 'Small gap', 10000, 9900);
    undercutProduct($this->user, 'Big gap', 10000, 8000);
    undercutProduct($this->user, 'Cheapest already', 9000, 9500);

    $this->get(route('dashboard'))
        ->assertSeeInOrder(['Big gap', '25.0%', 'Small gap', '1.0%'])
        ->assertDontSee('Cheapest already');
});

it('shows price changes from the last 24 hours with the previous price', function () {
    $product = undercutProduct($this->user, 'Console', 50000, 45000);
    $listing = $product->listings()->where('is_own', false)->sole();
    PricePoint::factory()->for($listing)->create(['price' => 47000, 'recorded_at' => now()->subDays(3)]);
    PricePoint::factory()->for($listing)->create(['price' => 45000, 'recorded_at' => now()->subHours(2)]);
    PricePoint::factory()->for($listing)->create(['price' => 45000, 'recorded_at' => now()->subHour()]); // heartbeat, not a change

    $this->get(route('dashboard'))
        ->assertSeeInOrder(['Rival of Console', '470,00 €', '450,00 €'])
        ->assertSee('−4.3%');
});

it('has honest empty states', function () {
    $this->get(route('dashboard'))
        ->assertSee('Add your first product')
        ->assertSee('No price changes in the last 24 hours.');
});

it('draws a price ladder that names every store price for screen readers', function () {
    undercutProduct($this->user, 'Console', 50000, 45000);

    $this->get(route('dashboard'))->assertSee('Mine 500,00 € (you), Rival of Console 450,00 €');
});

it('ignores other users\' data', function () {
    undercutProduct(User::factory()->create(), 'Not mine', 10000, 5000);

    $this->get(route('dashboard'))->assertDontSee('Not mine');
});

it('does not count paused links as failing', function () {
    Listing::factory()->for(Product::factory()->for($this->user))->create(['status' => ListingStatus::Paused]);

    $this->get(route('dashboard'))->assertDontSee('failing link');
});
