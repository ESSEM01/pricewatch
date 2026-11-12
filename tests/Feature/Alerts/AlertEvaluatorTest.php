<?php

use App\Enums\AlertRuleType;
use App\Events\PriceChanged;
use App\Models\AlertRule;
use App\Models\Listing;
use App\Models\Product;
use App\Models\User;
use App\Notifications\PriceAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Carbon::setTestNow('2026-11-12 10:00:00');
    Notification::fake();

    $this->user = User::factory()->create();
    $this->product = Product::factory()->for($this->user)->create();
    $this->own = Listing::factory()->for($this->product)->own()->create(['current_price' => 10000]);
    $this->rival = Listing::factory()->for($this->product)->create(['current_price' => 10500]);
});

function rule(AlertRuleType $type, ?int $threshold = null, array $attributes = []): AlertRule
{
    return AlertRule::factory()->for(test()->product)->create([
        'user_id' => test()->user->id,
        'type' => $type,
        'threshold' => $threshold,
        ...$attributes,
    ]);
}

function priceMoves(Listing $listing, int $from, int $to): void
{
    $listing->forceFill(['current_price' => $to])->save();
    event(new PriceChanged($listing, $from, $to));
}

it('alerts when a price drops below the threshold', function () {
    $rule = rule(AlertRuleType::BelowThreshold, 10000);

    priceMoves($this->rival, 10500, 9900);

    Notification::assertSentTo($this->user, PriceAlert::class, fn (PriceAlert $alert) => $alert->rule->is($rule)
        && $alert->listing->is($this->rival)
        && $alert->previousPrice === 10500
        && $alert->newPrice === 9900);
    expect($rule->fresh()->last_triggered_at?->toDateTimeString())->toBe('2026-11-12 10:00:00');
});

it('stays quiet when the price was already below the threshold', function () {
    rule(AlertRuleType::BelowThreshold, 10000);

    priceMoves($this->rival, 9900, 9800);

    Notification::assertNothingSent();
});

it('alerts when a competitor goes below the user\'s own price', function () {
    rule(AlertRuleType::CompetitorUndercut);

    priceMoves($this->rival, 10500, 9990);

    Notification::assertSentTo($this->user, PriceAlert::class);
});

it('ignores the user\'s own price moves and competitors already below', function () {
    rule(AlertRuleType::CompetitorUndercut);

    priceMoves($this->own, 10000, 9000);
    priceMoves($this->rival, 8900, 8800);

    Notification::assertNothingSent();
});

it('alerts on a large move either way', function (int $from, int $to, bool $alerts) {
    rule(AlertRuleType::ChangePercent, 500); // 5%

    priceMoves($this->rival, $from, $to);

    $alerts ? Notification::assertSentTo($this->user, PriceAlert::class) : Notification::assertNothingSent();
})->with([
    'drop of 6%' => [10000, 9400, true],
    'rise of 5%' => [10000, 10500, true],
    'drop of 4%' => [10000, 9600, false],
]);

it('sends one alert per rule per 24 hours', function () {
    rule(AlertRuleType::ChangePercent, 500);

    priceMoves($this->rival, 10000, 9000);
    Carbon::setTestNow('2026-11-12 20:00:00');
    priceMoves($this->rival, 9000, 10000);
    Notification::assertSentToTimes($this->user, PriceAlert::class, 1);

    Carbon::setTestNow('2026-11-13 10:00:01');
    priceMoves($this->rival, 10000, 9000);
    Notification::assertSentToTimes($this->user, PriceAlert::class, 2);
});

it('skips inactive rules and other products\' rules', function () {
    rule(AlertRuleType::ChangePercent, 100, ['is_active' => false]);
    AlertRule::factory()->changePercent(100)->create();

    priceMoves($this->rival, 10000, 5000);

    Notification::assertNothingSent();
});

it('is delivered by mail and in the app, with a readable summary', function () {
    $rule = rule(AlertRuleType::BelowThreshold, 10000);
    $alert = new PriceAlert($rule, $this->rival, 10500, 9900);

    expect($alert->via($this->user))->toBe(['mail', 'database'])
        ->and($alert->toArray($this->user))->toMatchArray([
            'product_id' => $this->product->id,
            'listing_id' => $this->rival->id,
            'previous_price' => 10500,
            'new_price' => 9900,
        ])
        ->and($alert->toArray($this->user)['message'])->toContain('99,00 €')->toContain('below 100,00 €');

    $mail = $alert->toMail($this->user);
    expect($mail->subject)->toContain($this->product->name)
        ->and($mail->actionUrl)->toBe(route('products.show', $this->product));
});
