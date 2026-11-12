<?php

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\PricePoint;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Overview')] class extends Component {
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    #[Computed]
    public function products(): \Illuminate\Database\Eloquent\Collection
    {
        return Auth::user()->products()->with('listings.store')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Listing>
     */
    #[Computed]
    public function listings(): Collection
    {
        return $this->products->flatMap(fn (Product $product) => $product->listings->each->setRelation('product', $product));
    }

    /**
     * Products where a competitor is cheaper, largest gap first.
     *
     * @return Collection<int, array{product: Product, own: Listing, cheapest: Listing, gap: float}>
     */
    #[Computed]
    public function undercut(): Collection
    {
        return $this->products
            ->map(function (Product $product): ?array {
                $own = $product->listings->firstWhere('is_own', true);
                $cheapest = $product->cheapestCompetitor();

                if (! $own?->current_price || ! $cheapest?->current_price || $cheapest->current_price >= $own->current_price) {
                    return null;
                }

                $gap = ($own->current_price - $cheapest->current_price) / $cheapest->current_price * 100;

                return ['product' => $product, 'own' => $own, 'cheapest' => $cheapest, 'gap' => $gap];
            })
            ->filter()
            ->sortByDesc('gap')
            ->values();
    }

    /**
     * Price changes in the last 24 hours. LAG() pairs every stored point with
     * the one before it, so daily heartbeats (same price) drop out.
     */
    private function changesQuery(): Builder
    {
        $withPrevious = PricePoint::query()
            ->select(['id', 'listing_id', 'price', 'currency', 'recorded_at'])
            ->selectRaw('LAG(price) OVER (PARTITION BY listing_id ORDER BY recorded_at, id) AS previous_price')
            ->whereIn('listing_id', $this->listings->pluck('id')->all())
            ->where('recorded_at', '>=', now()->subDays(30));

        return DB::query()
            ->fromSub($withPrevious, 'p')
            ->where('recorded_at', '>=', now()->subDay())
            ->whereNotNull('previous_price')
            ->whereColumn('previous_price', '!=', 'price');
    }

    /**
     * @return Collection<int, object>
     */
    #[Computed]
    public function changes(): Collection
    {
        $listings = $this->listings->keyBy('id');

        return $this->changesQuery()->orderByDesc('recorded_at')->limit(8)->get()
            ->map(fn (object $row) => (object) [
                'listing' => $listings[$row->listing_id],
                'previous' => (int) $row->previous_price,
                'price' => (int) $row->price,
                'currency' => $row->currency,
                'change' => ($row->price - $row->previous_price) / max(1, $row->previous_price) * 100,
                'at' => \Illuminate\Support\Carbon::parse($row->recorded_at),
            ]);
    }

    #[Computed]
    public function changesCount(): int
    {
        return $this->changesQuery()->count();
    }

    #[Computed]
    public function failing(): int
    {
        return $this->listings->where('status', ListingStatus::Failing)->count();
    }

    #[Computed]
    public function lastCheck(): ?\Carbon\CarbonInterface
    {
        return $this->listings->max('last_checked_at');
    }
}; ?>

<div class="flex w-full flex-col gap-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Overview') }}</flux:heading>
            <flux:text class="mt-1">
                {{ $this->lastCheck ? __('Last price check :when.', ['when' => $this->lastCheck->diffForHumans()]) : __('No prices checked yet.') }}
            </flux:text>
        </div>
        <flux:button :href="route('products.index')" wire:navigate icon="plus" size="sm">{{ __('Add a product') }}</flux:button>
    </div>

    <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-zinc-200 bg-zinc-200 sm:grid-cols-4 dark:border-zinc-700 dark:bg-zinc-700">
        @foreach ([
            __('Products') => $this->products->count(),
            __('Store links') => $this->listings->count(),
            __('Price changes, 24h') => $this->changesCount,
            __('Undercut products') => $this->undercut->count(),
        ] as $label => $value)
            <div class="bg-white px-4 py-3 dark:bg-zinc-800">
                <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($this->failing > 0)
        <flux:callout icon="exclamation-triangle" color="red">
            <flux:callout.text>
                {{ trans_choice(':count failing link: its price could not be read. Open the product to see why.|:count failing links: their prices could not be read. Open the products to see why.', $this->failing) }}
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->products->isEmpty())
        <flux:callout icon="shopping-bag">
            <flux:callout.heading>{{ __('Add your first product') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Create a product, paste the store pages that sell it, and PriceWatch starts checking prices every few hours.') }}
                <flux:link :href="route('products.index')" wire:navigate>{{ __('Go to products') }}</flux:link>
            </flux:callout.text>
        </flux:callout>
    @endif

    <section aria-labelledby="undercut-heading" class="flex flex-col gap-3">
        <flux:heading id="undercut-heading" size="lg">{{ __('Where a competitor is cheaper') }}</flux:heading>

        @if ($this->undercut->isEmpty())
            <flux:text>{{ $this->products->isEmpty() ? '—' : __('Nobody is cheaper than you right now.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Product') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Your price') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Cheapest competitor') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('You are above by') }}</flux:table.column>
                    <flux:table.column class="max-sm:hidden">{{ __('Price ladder') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->undercut as $row)
                        <flux:table.row :key="$row['product']->id">
                            <flux:table.cell>
                                <flux:link :href="route('products.show', $row['product'])" wire:navigate class="font-medium">{{ $row['product']->name }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Money::format($row['own']->current_price, $row['own']->currency) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">
                                {{ Money::format($row['cheapest']->current_price, $row['cheapest']->currency) }}
                                <span class="text-zinc-500">· {{ $row['cheapest']->store->name }}</span>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums text-red-700 dark:text-red-400">{{ number_format($row['gap'], 1) }}%</flux:table.cell>
                            <flux:table.cell class="max-sm:hidden"><x-price-ladder :listings="$row['product']->listings" /></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </section>

    <section aria-labelledby="changes-heading" class="flex flex-col gap-3">
        <flux:heading id="changes-heading" size="lg">{{ __('Price changes') }}</flux:heading>

        @if ($this->changes->isEmpty())
            <flux:text>{{ __('No price changes in the last 24 hours.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Store') }}</flux:table.column>
                    <flux:table.column>{{ __('Product') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Was') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Now') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Change') }}</flux:table.column>
                    <flux:table.column align="end" class="max-sm:hidden">{{ __('When') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->changes as $change)
                        <flux:table.row :key="$change->listing->id.'-'.$change->at->timestamp">
                            <flux:table.cell class="font-medium">{{ $change->listing->store->name }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:link :href="route('products.show', $change->listing->product_id)" wire:navigate>{{ $change->listing->product->name }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums text-zinc-500">{{ Money::format($change->previous, $change->currency) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ Money::format($change->price, $change->currency) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ $change->change >= 0 ? '+' : '−' }}{{ number_format(abs($change->change), 1) }}%</flux:table.cell>
                            <flux:table.cell align="end" class="max-sm:hidden text-zinc-500">{{ $change->at->diffForHumans() }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </section>
</div>
