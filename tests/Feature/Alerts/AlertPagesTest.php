<?php

use App\Enums\AlertRuleType;
use App\Models\AlertRule;
use App\Models\Listing;
use App\Models\Product;
use App\Models\User;
use App\Notifications\PriceAlert;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->product = Product::factory()->for($this->user)->create();
});

it('adds alert rules with thresholds in euros or percent', function () {
    $component = Livewire::test('pages::products.show', ['product' => $this->product]);

    $component->set('ruleType', 'below_threshold')->set('ruleThreshold', '499,90')->call('addRule')->assertHasNoErrors();
    $component->set('ruleType', 'change_percent')->set('ruleThreshold', '7.5')->call('addRule')->assertHasNoErrors();
    $component->set('ruleType', 'competitor_undercut')->set('ruleThreshold', '')->call('addRule')->assertHasNoErrors();

    expect($this->product->alertRules()->orderBy('id')->get(['type', 'threshold', 'user_id'])->toArray())->toBe([
        ['type' => 'below_threshold', 'threshold' => 49990, 'user_id' => $this->user->id],
        ['type' => 'change_percent', 'threshold' => 750, 'user_id' => $this->user->id],
        ['type' => 'competitor_undercut', 'threshold' => null, 'user_id' => $this->user->id],
    ]);
});

it('validates thresholds', function (string $type, string $threshold) {
    Livewire::test('pages::products.show', ['product' => $this->product])
        ->set('ruleType', $type)
        ->set('ruleThreshold', $threshold)
        ->call('addRule')
        ->assertHasErrors('ruleThreshold');

    expect(AlertRule::query()->count())->toBe(0);
})->with([
    'missing price' => ['below_threshold', ''],
    'not a number' => ['below_threshold', 'cheap'],
    'zero price' => ['below_threshold', '0'],
    'percent over 100' => ['change_percent', '150'],
    'percent zero' => ['change_percent', '0'],
]);

it('rejects unknown rule types', function () {
    Livewire::test('pages::products.show', ['product' => $this->product])
        ->set('ruleType', 'everything')
        ->call('addRule')
        ->assertHasErrors('ruleType');
});

it('pauses and deletes rules, only on its own product', function () {
    $rule = AlertRule::factory()->for($this->product)->create(['user_id' => $this->user->id]);
    $foreign = AlertRule::factory()->create();
    $component = Livewire::test('pages::products.show', ['product' => $this->product]);

    $component->call('toggleRule', $rule->id);
    expect($rule->fresh()->is_active)->toBeFalse();

    $component->call('deleteRule', $rule->id);
    expect(AlertRule::query()->find($rule->id))->toBeNull();

    $component->call('deleteRule', $foreign->id)->assertNotFound();
    expect($foreign->fresh())->not->toBeNull();
});

it('lists alerts, newest first, and marks them read', function () {
    $listing = Listing::factory()->for($this->product)->create();
    $rule = AlertRule::factory()->for($this->product)->create(['user_id' => $this->user->id, 'type' => AlertRuleType::ChangePercent, 'threshold' => 500]);
    $this->user->notify(new PriceAlert($rule, $listing, 10000, 9000));

    $this->get(route('alerts.index'))->assertOk()->assertSee('90,00 €')->assertSee($this->product->name);
    expect($this->user->unreadNotifications()->count())->toBe(1);

    Livewire::test('pages::alerts.index')->call('markAllRead');

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

it('shows the unread count in the sidebar', function () {
    $listing = Listing::factory()->for($this->product)->create();
    $rule = AlertRule::factory()->for($this->product)->create(['user_id' => $this->user->id]);
    $this->user->notify(new PriceAlert($rule, $listing, 10000, 9000));

    $this->get(route('products.index'))->assertSee('data-unread-alerts="1"', false);
});
