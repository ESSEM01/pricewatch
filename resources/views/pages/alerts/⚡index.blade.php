<?php

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Alerts')] class extends Component {
    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function alerts(): Collection
    {
        return Auth::user()->notifications()->latest()->limit(100)->get();
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        unset($this->alerts);
        $this->dispatch('alerts-read');
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Alerts') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Price changes that matched one of your rules. Set rules on each product page.') }}</flux:text>
        </div>
        @if ($this->alerts->whereNull('read_at')->isNotEmpty())
            <flux:button wire:click="markAllRead" icon="check" size="sm">{{ __('Mark all as read') }}</flux:button>
        @endif
    </div>

    @if ($this->alerts->isEmpty())
        <flux:callout icon="bell">
            <flux:callout.heading>{{ __('No alerts yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Add a rule on a product, for example "a competitor goes below my price".') }}</flux:callout.text>
        </flux:callout>
    @else
        <ul class="flex flex-col divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->alerts as $alert)
                <li wire:key="{{ $alert->id }}" class="flex flex-col gap-1 p-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-3">
                        @if ($alert->read_at === null)
                            <span class="mt-2 size-2 shrink-0 rounded-full bg-blue-500" aria-hidden="true"></span>
                            <span class="sr-only">{{ __('Unread') }}</span>
                        @else
                            <span class="mt-2 size-2 shrink-0" aria-hidden="true"></span>
                        @endif
                        <div>
                            <flux:link :href="route('products.show', $alert->data['product_id'])" wire:navigate class="font-medium">
                                {{ $alert->data['product_name'] }}
                            </flux:link>
                            <flux:text>{{ $alert->data['message'] }}</flux:text>
                        </div>
                    </div>
                    <flux:text size="sm" class="shrink-0 ps-5 sm:ps-0">
                        <time datetime="{{ $alert->created_at?->toIso8601String() }}">{{ $alert->created_at?->diffForHumans() }}</time>
                    </flux:text>
                </li>
            @endforeach
        </ul>
    @endif
</div>
