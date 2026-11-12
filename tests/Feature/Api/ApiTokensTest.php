<?php

use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('shows the tokens page', function () {
    $this->get(route('api-tokens.edit'))->assertOk()->assertSee('API tokens');
});

it('creates a read-only token and shows it once', function () {
    $component = Livewire::test('pages::settings.api-tokens')
        ->set('tokenName', 'Spreadsheet')
        ->call('createToken')
        ->assertHasNoErrors();

    $plain = $component->get('plainTextToken');
    expect($plain)->toBeString()->not->toBeEmpty()
        ->and($this->user->tokens()->sole())
        ->name->toBe('Spreadsheet')
        ->abilities->toBe(['read']);

    // The token works against the API.
    auth()->forgetGuards();
    $this->withToken($plain)->getJson('/api/products')->assertOk();
});

it('requires a token name', function () {
    Livewire::test('pages::settings.api-tokens')->set('tokenName', '')->call('createToken')->assertHasErrors('tokenName');
});

it('revokes a token, only the user\'s own', function () {
    $mine = $this->user->createToken('mine', ['read'])->accessToken;
    $foreign = User::factory()->create()->createToken('theirs', ['read'])->accessToken;
    $component = Livewire::test('pages::settings.api-tokens');

    $component->call('revokeToken', $mine->id);
    expect($this->user->tokens()->count())->toBe(0);

    $component->call('revokeToken', $foreign->id)->assertNotFound();
    expect($foreign->fresh())->not->toBeNull();
});
