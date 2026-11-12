<?php

use App\Enums\ListingStatus;
use App\Jobs\ScrapeListing;
use App\Models\Listing;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function demoUrl(string $store = 'tecnoshop', string $product = 'ps5-slim'): string
{
    return rtrim((string) config('app.url'), '/')."/demo-shop/{$store}/{$product}";
}

it('keeps guests out', function () {
    auth()->logout();

    $this->get(route('products.index'))->assertRedirect(route('login'));
});

it('lists only the user\'s own products, with prices', function () {
    $mine = Product::factory()->for($this->user)->create(['name' => 'My console']);
    Listing::factory()->for($mine)->own()->create(['current_price' => 49990]);
    Listing::factory()->for($mine)->create(['current_price' => 45990]);
    Product::factory()->create(['name' => 'Somebody else\'s TV']);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertSee('My console')
        ->assertSee('499,90 €')
        ->assertSee('459,90 €')
        ->assertDontSee('Somebody else');
});

it('creates a product and opens it', function () {
    Livewire::test('pages::products.index')
        ->set('name', 'PlayStation 5 Slim')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect(route('products.show', Product::query()->sole()));

    expect(Product::query()->sole()->user_id)->toBe($this->user->id);
});

it('validates the product name', function (string $name) {
    Livewire::test('pages::products.index')
        ->set('name', $name)
        ->call('create')
        ->assertHasErrors('name');
})->with(['empty' => [''], 'too long' => [str_repeat('x', 121)]]);

it('forbids opening another user\'s product', function () {
    $this->get(route('products.show', Product::factory()->create()))->assertForbidden();
});

it('adds a demo shop link, resolves its store and queues a first check', function () {
    $product = Product::factory()->for($this->user)->create();

    Livewire::test('pages::products.show', ['product' => $product])
        ->set('url', demoUrl())
        ->set('isOwn', true)
        ->call('addListing')
        ->assertHasNoErrors()
        ->assertSet('url', '');

    $listing = $product->listings()->sole();
    expect($listing->store->name)->toBe('TecnoShop')
        ->and($listing->is_own)->toBeTrue();
    Queue::assertPushed(ScrapeListing::class, fn (ScrapeListing $job) => $job->listing->is($listing));
});

it('rejects links to private addresses and other schemes', function (string $url) {
    $product = Product::factory()->for($this->user)->create();

    Livewire::test('pages::products.show', ['product' => $product])
        ->set('url', $url)
        ->call('addListing')
        ->assertHasErrors('url');

    expect($product->listings()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'private network' => ['http://10.0.0.1/product'],
    'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
    'file scheme' => ['file:///etc/passwd'],
    'not a url' => ['playstation'],
]);

it('refuses a second link on the same store', function () {
    $product = Product::factory()->for($this->user)->create();
    $component = Livewire::test('pages::products.show', ['product' => $product]);

    $component->set('url', demoUrl('tecnoshop', 'ps5-slim'))->call('addListing')->assertHasNoErrors();
    $component->set('url', demoUrl('tecnoshop', 'switch-2'))->call('addListing')->assertHasErrors('url');

    expect($product->listings()->count())->toBe(1);
});

it('keeps a single own listing per product', function () {
    $product = Product::factory()->for($this->user)->create();
    $old = Listing::factory()->for($product)->own()->create();

    Livewire::test('pages::products.show', ['product' => $product])
        ->set('url', demoUrl('elettrocasa'))
        ->set('isOwn', true)
        ->call('addListing');

    expect($old->fresh()->is_own)->toBeFalse()
        ->and($product->listings()->where('is_own', true)->count())->toBe(1);
});

it('checks, pauses, resumes and removes a listing', function () {
    $product = Product::factory()->for($this->user)->create();
    $listing = Listing::factory()->for($product)->failing()->create();
    $component = Livewire::test('pages::products.show', ['product' => $product]);

    $component->call('checkNow', $listing->id);
    Queue::assertPushed(ScrapeListing::class, 1);

    $component->call('togglePause', $listing->id);
    expect($listing->fresh()->status)->toBe(ListingStatus::Paused);

    $component->call('togglePause', $listing->id);
    expect($listing->fresh())
        ->status->toBe(ListingStatus::Active)
        ->consecutive_failures->toBe(0)
        ->next_check_at->toBeNull();

    $component->call('removeListing', $listing->id);
    expect(Listing::query()->find($listing->id))->toBeNull();
});

it('cannot touch a listing that belongs to another product', function () {
    $product = Product::factory()->for($this->user)->create();
    $foreign = Listing::factory()->create();

    Livewire::test('pages::products.show', ['product' => $product])
        ->call('removeListing', $foreign->id)
        ->assertNotFound();

    expect($foreign->fresh())->not->toBeNull();
});

it('deletes the product with its listings', function () {
    $product = Product::factory()->for($this->user)->create();
    Listing::factory()->for($product)->for(Store::factory())->create();

    Livewire::test('pages::products.show', ['product' => $product])
        ->call('deleteProduct')
        ->assertRedirect(route('products.index'));

    expect(Product::query()->count())->toBe(0)
        ->and(Listing::query()->count())->toBe(0);
});
