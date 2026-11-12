<?php

use App\Enums\ListingStatus;
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
        return $this->product->listings()->with('store')
            ->orderByDesc('is_own')
            ->orderByRaw('current_price IS NULL, current_price')
            ->get();
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
