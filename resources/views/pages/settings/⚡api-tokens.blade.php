<?php

use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('API tokens')] class extends Component {
    public string $tokenName = '';

    /** Shown once, right after creation; never stored in plain text. */
    public ?string $plainTextToken = null;

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PersonalAccessToken>
     */
    #[Computed]
    public function tokens(): \Illuminate\Database\Eloquent\Collection
    {
        return Auth::user()->tokens()->latest()->get();
    }

    public function createToken(): void
    {
        $this->validate(['tokenName' => ['required', 'string', 'max:60']]);

        $this->plainTextToken = Auth::user()->createToken($this->tokenName, ['read'])->plainTextToken;

        $this->reset('tokenName');
        unset($this->tokens);
    }

    public function revokeToken(int $tokenId): void
    {
        Auth::user()->tokens()->whereKey($tokenId)->firstOrFail()->delete();

        unset($this->tokens);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('API tokens') }}</flux:heading>

    <x-pages::settings.layout :heading="__('API tokens')" :subheading="__('Read-only access to your products, prices and forecasts. See docs/API.md.')">
        <form wire:submit="createToken" class="my-6 flex flex-col gap-3 sm:flex-row sm:items-end">
            <flux:input wire:model="tokenName" :label="__('Token name')" :placeholder="__('e.g. Spreadsheet')" required />
            <flux:button type="submit" variant="primary">{{ __('Create token') }}</flux:button>
        </form>

        @if ($plainTextToken)
            <flux:callout icon="key" color="amber" class="mb-6">
                <flux:callout.heading>{{ __('Copy your new token now') }}</flux:callout.heading>
                <flux:callout.text>{{ __('For your security it will not be shown again.') }}</flux:callout.text>
                <flux:input readonly :value="$plainTextToken" copyable class="mt-2 font-mono" :aria-label="__('New API token')" />
            </flux:callout>
        @endif

        @forelse ($this->tokens as $token)
            <div wire:key="token-{{ $token->id }}" class="flex items-center justify-between gap-3 border-t border-zinc-200 py-3 dark:border-zinc-700">
                <div>
                    <flux:text class="font-medium">{{ $token->name }}</flux:text>
                    <flux:text size="sm">
                        {{ $token->last_used_at ? __('Last used :when', ['when' => $token->last_used_at->diffForHumans()]) : __('Never used') }}
                    </flux:text>
                </div>
                <flux:button size="sm" variant="ghost" wire:click="revokeToken({{ $token->id }})" wire:confirm="{{ __('Revoke this token? Apps using it will stop working.') }}">
                    {{ __('Revoke') }}
                </flux:button>
            </div>
        @empty
            <flux:text>{{ __('No tokens yet.') }}</flux:text>
        @endforelse
    </x-pages::settings.layout>
</section>
