<?php

use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Livewire\Livewire;

/*
| The demo account is shared by every visitor and its password is public:
| nobody may change its credentials, 2FA, tokens, or delete it.
*/

beforeEach(function () {
    Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]);

    $this->demo = User::factory()->create(['email' => DemoDataSeeder::EMAIL, 'name' => 'Demo']);
    $this->actingAs($this->demo)->withSession(['auth.password_confirmed_at' => time()]);
});

it('knows which user is the demo one', function () {
    expect($this->demo->isDemo())->toBeTrue()
        ->and(User::factory()->create()->isDemo())->toBeFalse();
});

it('still shows the settings pages to the demo user', function (string $route) {
    $this->get(route($route))->assertOk()->assertSee('read-only');
})->with(['profile.edit', 'security.edit', 'api-tokens.edit', 'appearance.edit']);

it('does not let the demo user edit the profile', function () {
    Livewire::test('pages::settings.profile')
        ->set('name', 'Pwned')
        ->set('email', 'attacker@example.com')
        ->call('updateProfileInformation')
        ->assertForbidden();

    expect($this->demo->fresh())->name->toBe('Demo')->email->toBe(DemoDataSeeder::EMAIL);
});

it('does not let the demo user change the password', function () {
    Livewire::test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword')
        ->assertForbidden();

    expect(Hash::check('password', $this->demo->fresh()->password))->toBeTrue();
});

it('does not let the demo user delete the account', function () {
    Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertForbidden();

    expect($this->demo->fresh())->not->toBeNull();
    $this->assertAuthenticatedAs($this->demo);
});

it('does not let the demo user turn on two-factor authentication', function () {
    Livewire::test('pages::settings.two-factor-setup-modal', ['requiresConfirmation' => true])
        ->dispatch('start-two-factor-setup')
        ->assertForbidden();

    Livewire::test('pages::settings.two-factor-setup-modal', ['requiresConfirmation' => true])
        ->set('code', '123456')
        ->call('confirmTwoFactor')
        ->assertForbidden();

    expect($this->demo->fresh()->two_factor_secret)->toBeNull();
});

it('does not let the demo user disable two-factor or rotate recovery codes', function () {
    $this->demo->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code-1'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    Livewire::test('pages::settings.security')->call('disable')->assertForbidden();
    Livewire::test('pages::settings.two-factor.recovery-codes', ['requiresConfirmation' => true])
        ->call('regenerateRecoveryCodes')
        ->assertForbidden();

    expect($this->demo->fresh())
        ->two_factor_confirmed_at->not->toBeNull()
        ->and(json_decode(decrypt($this->demo->fresh()->two_factor_recovery_codes)))->toBe(['code-1']);
});

it('does not let the demo user manage api tokens', function () {
    $token = $this->demo->createToken('existing', ['read'])->accessToken;

    Livewire::test('pages::settings.api-tokens')->set('tokenName', 'mine')->call('createToken')->assertForbidden();
    Livewire::test('pages::settings.api-tokens')->call('revokeToken', $token->id)->assertForbidden();

    expect($this->demo->tokens()->pluck('name')->all())->toBe(['existing']);
});

it('does not let the demo user delete passkeys', function () {
    Livewire::test('pages::settings.security')->call('deletePasskey')->assertForbidden();
});

it('still allows the actions that only show things', function () {
    Livewire::test('pages::settings.security')
        ->call('loadPasskeys')->assertOk()
        ->call('closeDeleteModal')->assertOk();

    Livewire::test('pages::settings.two-factor.recovery-codes', ['requiresConfirmation' => true])
        ->call('viewRecoveryCodes')
        ->assertOk();
});

it('blocks the account endpoints for the demo user', function (string $method, string $route, array $parameters = []) {
    $this->json($method, route($route, $parameters))->assertForbidden();
})->with([
    'enable 2FA' => ['POST', 'two-factor.enable'],
    'confirm 2FA' => ['POST', 'two-factor.confirm'],
    'disable 2FA' => ['DELETE', 'two-factor.disable'],
    'new recovery codes' => ['POST', 'two-factor.recovery-codes'],
    'register a passkey' => ['POST', 'passkey.store'],
    'delete a passkey' => ['DELETE', 'passkey.destroy', ['passkey' => 1]],
]);

it('still lets the demo user confirm the password and log out', function () {
    $this->post(route('password.confirm.store'), ['password' => 'password'])->assertSessionHasNoErrors();

    $this->post(route('logout'))->assertRedirect();
    $this->assertGuest();
});

it('leaves other users free to manage their account', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('profile.edit'))->assertOk()->assertDontSee('read-only');
    Livewire::test('pages::settings.api-tokens')->set('tokenName', 'mine')->call('createToken')->assertHasNoErrors();

    expect($user->tokens()->count())->toBe(1);
});

it('turns the demo login off by default outside local development', function () {
    expect(app()->environment())->not->toBe('local')
        ->and((require config_path('demo_shop.php'))['demo_login'])->toBeFalse();
});
