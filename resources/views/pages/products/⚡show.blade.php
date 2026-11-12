<?php

use App\Enums\AlertRuleType;
use App\Enums\ListingStatus;
use App\Models\AlertRule;
use App\Scraping\Money\PriceParser;
use Illuminate\Validation\Rule;
use App\Forecasting\Forecaster;
use App\Forecasting\Signals\WeeklyPromoDetector;
use App\Jobs\ScrapeListing;
use App\Models\Listing;
use App\Models\Product;
use App\Rules\SafeUrl;
use App\Scraping\StoreResolver;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Product $product;

    public string $url = '';

    public bool $isOwn = false;

    public string $ruleType = 'competitor_undercut';

    /** Euros for "below", percent for "change", unused for "undercut". */
    public string $ruleThreshold = '';

    public function mount(Product $product): void
    {
        $this->authorize('view', $product);

        $this->product = $product;
    }

    /**
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function listings(): Collection
    {
        return $this->product->listings()->with(['store', 'forecast'])
            ->orderByDesc('is_own')
            ->orderByRaw('current_price IS NULL, current_price')
            ->get();
    }

    /**
     * Chart series in a stable order (own store first, then by name), so each
     * store keeps its colour when prices move.
     *
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function chartListings(): Collection
    {
        return $this->listings->sortBy(fn (Listing $l) => [! $l->is_own, $l->store->name])->values();
    }

    /**
     * @return array{today: int, series: list<array<string, mixed>>}
     */
    #[Computed]
    public function chartData(): array
    {
        $forecaster = app(Forecaster::class);
        $ms = fn (string|\Carbon\CarbonInterface $date): int => \Carbon\CarbonImmutable::parse($date)->startOfDay()->getTimestampMs();

        $series = $this->chartListings->map(function (Listing $listing) use ($forecaster, $ms): array {
            $history = $forecaster->history($listing)->lastDays(90);

            return [
                'name' => $listing->store->name,
                'own' => $listing->is_own,
                'history' => array_map(
                    fn (float $cents, int $i): array => [$ms($history->start()->addDays($i)), $cents / 100],
                    $history->values(),
                    array_keys($history->values()),
                ),
                'forecast' => $listing->forecast === null ? null : array_map(
                    fn (array $p): array => [$ms($p['date']), $p['price'] / 100, $p['lower'] / 100, $p['upper'] / 100],
                    $listing->forecast->points,
                ),
            ];
        })->all();

        return ['today' => $ms(now()), 'series' => $series];
    }

    /**
     * Plain-language takeaways shown above the chart.
     *
     * @return list<string>
     */
    #[Computed]
    public function insights(): array
    {
        $insights = [];
        $own = $this->listings->firstWhere('is_own', true);
        $this->product->setRelation('listings', $this->listings);
        $cheapest = $this->product->cheapestCompetitor();

        if ($own?->current_price && $cheapest?->current_price) {
            $gap = ($own->current_price - $cheapest->current_price) / $cheapest->current_price * 100;
            $name = $cheapest->store->name;

            $insights[] = match (true) {
                $gap > 0.05 => __('You are :pct% above the cheapest competitor (:store).', ['pct' => number_format($gap, 1), 'store' => $name]),
                $gap < -0.05 => __('You are the cheapest, :pct% below :store.', ['pct' => number_format(-$gap, 1), 'store' => $name]),
                default => __('You match the cheapest competitor (:store).', ['store' => $name]),
            };
        }

        $detector = new WeeklyPromoDetector;
        $forecaster = app(Forecaster::class);

        foreach ($this->listings as $listing) {
            $weekdays = $detector->detect($forecaster->history($listing));

            if ($weekdays !== []) {
                $names = array_map(fn (int $d) => \Carbon\CarbonImmutable::now()->startOfWeek()->addDays($d - 1)->locale('en')->dayName, $weekdays);
                $insights[] = __(':store often cuts its price on :days.', [
                    'store' => $listing->store->name,
                    'days' => \Illuminate\Support\Arr::join($names, ', ', ' and '),
                ]);
            }
        }

        return $insights;
    }

    public function addListing(StoreResolver $stores): void
    {
        $this->authorize('update', $this->product);

        $this->validate([
            'url' => ['required', 'string', 'max:2048', 'url:http,https', new SafeUrl],
            'isOwn' => ['boolean'],
        ]);

        $store = $stores->resolve($this->url);

        if ($this->product->listings()->where('store_id', $store->id)->exists()) {
            $this->addError('url', __('You already track this product on :store.', ['store' => $store->name]));

            return;
        }

        $listing = DB::transaction(function () use ($store): Listing {
            if ($this->isOwn) {
                $this->product->listings()->update(['is_own' => false]);
            }

            return $this->product->listings()->create([
                'url' => $this->url,
                'store_id' => $store->id,
                'is_own' => $this->isOwn,
            ]);
        });

        ScrapeListing::dispatch($listing);

        $this->reset('url', 'isOwn');
        Flux::toast(variant: 'success', text: __('Link added. The first check is on its way.'));
    }

    public function checkNow(int $listingId): void
    {
        ScrapeListing::dispatch($this->ownedListing($listingId));

        Flux::toast(text: __('Check queued.'));
    }

    public function togglePause(int $listingId): void
    {
        $listing = $this->ownedListing($listingId);

        $listing->forceFill($listing->status === ListingStatus::Paused
            ? ['status' => ListingStatus::Active, 'consecutive_failures' => 0, 'next_check_at' => null]
            : ['status' => ListingStatus::Paused])->save();
    }

    public function removeListing(int $listingId): void
    {
        $this->ownedListing($listingId)->delete();
    }

    /**
     * @return Collection<int, AlertRule>
     */
    #[Computed]
    public function rules(): Collection
    {
        return $this->product->alertRules()->orderBy('id')->get();
    }

    public function addRule(PriceParser $parser): void
    {
        $this->authorize('update', $this->product);

        $this->validate(['ruleType' => ['required', Rule::enum(AlertRuleType::class)]]);
        $type = AlertRuleType::from($this->ruleType);

        $threshold = match ($type) {
            AlertRuleType::CompetitorUndercut => null,
            AlertRuleType::BelowThreshold => $parser->parse($this->ruleThreshold)?->amount,
            AlertRuleType::ChangePercent => is_numeric($percent = str_replace(',', '.', trim($this->ruleThreshold)))
                ? (int) round((float) $percent * 100)
                : null,
        };

        $valid = match ($type) {
            AlertRuleType::CompetitorUndercut => true,
            AlertRuleType::BelowThreshold => $threshold !== null && $threshold > 0,
            AlertRuleType::ChangePercent => $threshold !== null && $threshold > 0 && $threshold <= 10_000,
        };

        if (! $valid) {
            $this->addError('ruleThreshold', $type === AlertRuleType::ChangePercent
                ? __('Enter a percentage between 0 and 100.')
                : __('Enter a price, e.g. 499,90.'));

            return;
        }

        $rule = new AlertRule(['type' => $type, 'threshold' => $threshold]);
        $rule->user()->associate($this->product->user);
        $this->product->alertRules()->save($rule);

        $this->reset('ruleThreshold');
        unset($this->rules);
        Flux::toast(variant: 'success', text: __('Alert rule added.'));
    }

    public function toggleRule(int $ruleId): void
    {
        $rule = $this->ownedRule($ruleId);
        $rule->forceFill(['is_active' => ! $rule->is_active])->save();
        unset($this->rules);
    }

    public function deleteRule(int $ruleId): void
    {
        $this->ownedRule($ruleId)->delete();
        unset($this->rules);
    }

    private function ownedRule(int $ruleId): AlertRule
    {
        $this->authorize('update', $this->product);

        return $this->product->alertRules()->findOrFail($ruleId);
    }

    public function deleteProduct(): void
    {
        $this->authorize('delete', $this->product);

        $this->product->delete();

        $this->redirectRoute('products.index', navigate: true);
    }

    /**
     * Scoped to this product, so a forged id from another account 404s.
     */
    private function ownedListing(int $listingId): Listing
    {
        $this->authorize('update', $this->product);

        return $this->product->listings()->findOrFail($listingId);
    }

    public function render(): mixed
    {
        return $this->view()->title($this->product->name);
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:link :href="route('products.index')" wire:navigate class="text-sm">← {{ __('Products') }}</flux:link>
            <flux:heading size="xl" level="1" class="mt-2">{{ $product->name }}</flux:heading>
            @if ($product->sku)
                <flux:text class="mt-1">{{ __('SKU') }} {{ $product->sku }}</flux:text>
            @endif
        </div>

        <flux:modal.trigger name="delete-product">
            <flux:button variant="ghost" icon="trash" size="sm">{{ __('Delete product') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if ($this->insights !== [])
        <ul class="flex flex-col gap-1">
            @foreach ($this->insights as $insight)
                <li><flux:text>{{ $insight }}</flux:text></li>
            @endforeach
        </ul>
    @endif

    @if ($this->listings->isNotEmpty())
        <section class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" aria-labelledby="chart-heading">
            <div wire:ignore data-price-chart>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <flux:heading id="chart-heading" size="lg">{{ __('Price history and 14-day forecast') }}</flux:heading>
                    <label class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Forecast for') }}
                        <select data-forecast-select class="rounded-md border border-zinc-300 bg-white px-2 py-1 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                            @foreach ($this->chartListings as $index => $listing)
                                <option value="{{ $index }}">{{ $listing->store->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div data-chart-canvas class="mt-2 min-h-80" role="img" aria-label="{{ __('Price history per store and the forecast of the selected store. The same data is available in the table below.') }}"></div>
                <script type="application/json" data-chart-data>@json($this->chartData)</script>
            </div>

            <ul class="mt-3 flex flex-col gap-1">
                @foreach ($this->chartListings as $listing)
                    <li>
                        <flux:text size="sm">
                            <span class="font-medium">{{ $listing->store->name }}:</span>
                            @if ($listing->forecast)
                                {{ __(':model, average error :mape% when tested on the last 4 weeks.', [
                                    'model' => match ($listing->forecast->model) {
                                        'holt_winters' => 'Holt-Winters (weekly seasonality)',
                                        'linear_trend' => __('Linear trend'),
                                        default => __('Moving average'),
                                    },
                                    'mape' => number_format((float) $listing->forecast->mape, 1),
                                ]) }}
                            @else
                                {{ __('Not enough history yet: forecasts start after :days days.', ['days' => Forecaster::MINIMUM_DAYS]) }}
                            @endif
                        </flux:text>
                    </li>
                @endforeach
            </ul>

            <details class="mt-3">
                <summary class="cursor-pointer text-sm text-zinc-600 dark:text-zinc-300">{{ __('Show the forecast as a table') }}</summary>
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full text-sm tabular-nums">
                        <thead>
                            <tr class="text-left text-zinc-500">
                                <th scope="col" class="py-1 pe-4 font-medium">{{ __('Date') }}</th>
                                @foreach ($this->chartListings as $listing)
                                    <th scope="col" class="py-1 pe-4 font-medium">{{ $listing->store->name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (range(0, Forecaster::HORIZON_DAYS - 1) as $day)
                                <tr class="border-t border-zinc-100 dark:border-zinc-700">
                                    <td class="py-1 pe-4">{{ now()->addDays($day + 1)->format('D d M') }}</td>
                                    @foreach ($this->chartListings as $listing)
                                        @php $point = $listing->forecast?->points[$day] ?? null; @endphp
                                        <td class="py-1 pe-4">
                                            @if ($point)
                                                {{ Money::format($point['price']) }}
                                                <span class="text-zinc-500">({{ Money::format($point['lower']) }} – {{ Money::format($point['upper']) }})</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </section>
    @endif

    <form wire:submit="addListing" class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:input wire:model="url" type="url" :label="__('Add a store link')" placeholder="https://…" required />
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:checkbox wire:model="isOwn" :label="__('This is my store')" />
            <flux:button type="submit" variant="primary" icon="plus">{{ __('Add link') }}</flux:button>
        </div>
        @if (config('demo_shop.enabled'))
            <flux:text size="sm">
                {{ __('Need a store to try? Use one of the') }}
                <flux:link :href="route('demo-shop.index')" target="_blank">{{ __('demo shop pages') }}</flux:link>.
            </flux:text>
        @endif
    </form>

    <div wire:poll.15s.visible>
        @if ($this->listings->isEmpty())
            <flux:callout icon="link">
                <flux:callout.heading>{{ __('No links yet') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Paste the product page of each store you want to watch.') }}</flux:callout.text>
            </flux:callout>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Store') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Price') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('In 14 days') }}</flux:table.column>
                    <flux:table.column>{{ __('Last check') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column align="end"><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->listings as $listing)
                        <flux:table.row :key="$listing->id">
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <flux:link :href="$listing->url" target="_blank" rel="noopener noreferrer" class="font-medium">{{ $listing->store->name }}</flux:link>
                                    @if ($listing->is_own)
                                        <flux:badge size="sm" color="blue">{{ __('You') }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Money::format($listing->current_price, $listing->currency) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">
                                @php $last = $listing->forecast?->points[array_key_last($listing->forecast->points ?? [])] ?? null; @endphp
                                @if ($last && $listing->current_price)
                                    @php $change = ($last['price'] - $listing->current_price) / $listing->current_price * 100; @endphp
                                    {{ Money::format($last['price'], $listing->currency) }}
                                    <span class="text-zinc-500">{{ $change >= 0 ? '+' : '−' }}{{ number_format(abs($change), 1) }}%</span>
                                @else
                                    <span class="text-zinc-500">—</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $listing->last_checked_at?->diffForHumans() ?? __('Never') }}</flux:table.cell>
                            <flux:table.cell>
                                @switch($listing->status)
                                    @case(ListingStatus::Failing)
                                        <flux:tooltip :content="$listing->last_error">
                                            <flux:badge color="red" size="sm">{{ __('Failing') }}</flux:badge>
                                        </flux:tooltip>
                                        @break
                                    @case(ListingStatus::Paused)
                                        <flux:badge color="zinc" size="sm">{{ __('Paused') }}</flux:badge>
                                        @break
                                    @default
                                        @if ($listing->last_error)
                                            <flux:tooltip :content="$listing->last_error">
                                                <flux:badge color="amber" size="sm">{{ __('Retrying') }}</flux:badge>
                                            </flux:tooltip>
                                        @else
                                            <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                        @endif
                                @endswitch
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" :aria-label="__('Actions for :store', ['store' => $listing->store->name])" />
                                    <flux:menu>
                                        <flux:menu.item icon="arrow-path" wire:click="checkNow({{ $listing->id }})">{{ __('Check now') }}</flux:menu.item>
                                        <flux:menu.item :icon="$listing->status === ListingStatus::Paused ? 'play' : 'pause'" wire:click="togglePause({{ $listing->id }})">
                                            {{ $listing->status === ListingStatus::Paused ? __('Resume') : __('Pause') }}
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item variant="danger" icon="trash" wire:click="removeListing({{ $listing->id }})" wire:confirm="{{ __('Stop tracking this store?') }}">
                                            {{ __('Remove') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>

    <section class="flex flex-col gap-3" aria-labelledby="alerts-heading">
        <flux:heading id="alerts-heading" size="lg">{{ __('Alerts') }}</flux:heading>

        @foreach ($this->rules as $rule)
            <div wire:key="rule-{{ $rule->id }}" class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                <flux:text @class(['line-through opacity-60' => ! $rule->is_active])>
                    {{ match ($rule->type) {
                        AlertRuleType::BelowThreshold => __('Any store goes below :price', ['price' => Money::format($rule->threshold)]),
                        AlertRuleType::CompetitorUndercut => __('A competitor goes below my price'),
                        AlertRuleType::ChangePercent => __('Any store moves by :pct% or more', ['pct' => rtrim(rtrim(number_format(($rule->threshold ?? 0) / 100, 2), '0'), '.')]),
                    } }}
                    @if ($rule->last_triggered_at)
                        <span class="text-zinc-500">· {{ __('last sent :when', ['when' => $rule->last_triggered_at->diffForHumans()]) }}</span>
                    @endif
                </flux:text>
                <div class="flex shrink-0 gap-1">
                    <flux:button size="sm" variant="ghost" wire:click="toggleRule({{ $rule->id }})">{{ $rule->is_active ? __('Pause') : __('Resume') }}</flux:button>
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteRule({{ $rule->id }})" :aria-label="__('Delete rule')" />
                </div>
            </div>
        @endforeach

        <form wire:submit="addRule" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <flux:select wire:model.live="ruleType" :label="__('Notify me when')" class="sm:max-w-xs">
                <flux:select.option value="competitor_undercut">{{ __('A competitor goes below my price') }}</flux:select.option>
                <flux:select.option value="below_threshold">{{ __('Any store goes below a price') }}</flux:select.option>
                <flux:select.option value="change_percent">{{ __('Any store moves by a percentage') }}</flux:select.option>
            </flux:select>
            @if ($ruleType !== 'competitor_undercut')
                <flux:input wire:model="ruleThreshold" :label="$ruleType === 'change_percent' ? __('Percent') : __('Price (€)')" :placeholder="$ruleType === 'change_percent' ? '5' : '499,90'" inputmode="decimal" class="sm:max-w-32" />
            @endif
            <flux:button type="submit" icon="bell">{{ __('Add alert') }}</flux:button>
        </form>
    </section>

    @script
    <script>
        const mount = () => window.PriceWatch.mountPriceChart($wire.$el.querySelector('[data-price-chart]'));
        window.PriceWatch ? mount() : window.addEventListener('pricewatch:ready', mount, { once: true });
    </script>
    @endscript

    <flux:modal name="delete-product" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete :name?', ['name' => $product->name]) }}</flux:heading>
                <flux:text class="mt-2">{{ __('Its links and price history are deleted too. This cannot be undone.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteProduct">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
