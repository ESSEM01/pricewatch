@props(['listings'])

{{--
    Where each store's price sits between the cheapest and the dearest:
    the user's own store is the solid dot. The full readout is in aria-label.
--}}
@php
    $priced = collect($listings)
        ->filter(fn ($l) => $l->current_price !== null)
        ->sortBy(fn ($l) => [! $l->is_own, $l->current_price])
        ->values();
    $min = $priced->min('current_price');
    $max = $priced->max('current_price');
    $position = fn (int $price): float => $max === $min ? 50.0 : ($price - $min) / ($max - $min) * 100;
    $summary = $priced->map(fn ($l) => $l->store->name.' '.\App\Support\Money::format($l->current_price, $l->currency).($l->is_own ? ' (you)' : ''))->join(', ');
@endphp

@if ($priced->isNotEmpty())
    <div {{ $attributes->class('relative h-4 w-32') }} role="img" aria-label="{{ $summary }}">
        <div class="absolute inset-x-0 top-1/2 h-px bg-zinc-300 dark:bg-zinc-600"></div>
        @foreach ($priced->reverse() as $listing)
            <span
                class="{{ $listing->is_own ? 'z-10 bg-[#2a78d6] ring-2 ring-white dark:bg-[#3987e5] dark:ring-zinc-900' : 'border border-zinc-500 bg-white dark:border-zinc-400 dark:bg-zinc-900' }} absolute top-1/2 size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full"
                style="left: {{ number_format($position($listing->current_price), 1, '.', '') }}%"
                title="{{ $listing->store->name }}: {{ \App\Support\Money::format($listing->current_price, $listing->currency) }}"
            ></span>
        @endforeach
    </div>
@endif
