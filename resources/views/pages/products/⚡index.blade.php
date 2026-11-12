<?php

use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Products')] class extends Component {
    public string $name = '';

    public string $sku = '';

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        return Auth::user()->products()->with('listings.store')->orderBy('name')->get();
    }

    public function create(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:64'],
        ]);

        $product = Auth::user()->products()->create([
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?: null,
        ]);

        $this->redirectRoute('products.show', $product, navigate: true);
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Products') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Each product groups the same item on several stores.') }}</flux:text>
    </div>

    <form wire:submit="create" class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <flux:input wire:model="name" :label="__('New product')" :placeholder="__('e.g. PlayStation 5 Slim')" class="sm:max-w-sm" required />
        <flux:input wire:model="sku" :label="__('SKU (optional)')" class="sm:max-w-40" />
        <flux:button type="submit" variant="primary" icon="plus">{{ __('Add product') }}</flux:button>
    </form>

    @if ($this->products->isEmpty())
        <flux:callout icon="shopping-bag">
            <flux:callout.heading>{{ __('No products yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Add a product, then paste the links of the stores that sell it.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Product') }}</flux:table.column>
                <flux:table.column>{{ __('Stores') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Your price') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Cheapest competitor') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->products as $product)
                    @php
                        $own = $product->listings->firstWhere('is_own', true);
                        $cheapest = $product->cheapestCompetitor();
                        $failing = $product->listings->where('status', \App\Enums\ListingStatus::Failing)->count();
                        $undercut = $own?->current_price !== null && $cheapest !== null && $cheapest->current_price < $own->current_price;
                    @endphp
                    <flux:table.row :key="$product->id">
                        <flux:table.cell>
                            <flux:link :href="route('products.show', $product)" wire:navigate class="font-medium">{{ $product->name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ $product->listings->count() }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ Money::format($own?->current_price, $own->currency ?? 'EUR') }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">
                            {{ Money::format($cheapest?->current_price, $cheapest->currency ?? 'EUR') }}
                            @if ($cheapest)
                                <flux:text size="sm" class="inline">· {{ $cheapest->store->name }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($failing > 0)
                                <flux:badge color="red" size="sm">{{ trans_choice(':count failing|:count failing', $failing) }}</flux:badge>
                            @elseif ($undercut)
                                <flux:badge color="amber" size="sm">{{ __('Undercut') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('OK') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
