<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Symfony\Component\HttpFoundation\Response;

/**
 * The demo account is shared by every visitor and its password is public, so
 * nobody may change its profile, password, 2FA, passkeys or API tokens, or
 * delete it. Browsing the settings pages stays allowed.
 */
final class ProtectDemoAccount
{
    private const MESSAGE = 'The demo account is read-only.';

    /** Routes that change the account; only their unsafe methods are blocked. */
    private const ROUTES = [
        'profile.edit',
        'security.edit',
        'appearance.edit',
        'api-tokens.edit',
        'user-profile-information.update',
        'user-password.update',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.regenerate-recovery-codes',
        'passkey.store',
        'passkey.destroy',
    ];

    /** Settings pages post their actions to Livewire's endpoint, not to their route. */
    private const LIVEWIRE_COMPONENTS = 'pages::settings.';

    /** Settings actions that only show or hide things. Anything else is refused. */
    private const READ_ONLY_ACTIONS = [
        '$refresh',
        'loadPasskeys',
        'confirmDelete',
        'closeDeleteModal',
        'viewRecoveryCodes',
        'showVerificationIfNecessary',
        'resetVerification',
        'closeModal',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() && $request->routeIs(self::ROUTES) && self::isDemo($request->user())) {
            abort(403, self::MESSAGE);
        }

        return $next($request);
    }

    /**
     * Refuses a Livewire action on a settings page for the demo user. Event
     * listeners ("__dispatch") count as actions, so they are refused too.
     */
    public static function guardLivewireCall(Component $component, string $method): void
    {
        if (str_starts_with($component->getName(), self::LIVEWIRE_COMPONENTS)
            && ! in_array($method, self::READ_ONLY_ACTIONS, true)
            && self::isDemo(Auth::user())) {
            abort(403, self::MESSAGE);
        }
    }

    private static function isDemo(mixed $user): bool
    {
        return $user instanceof User && $user->isDemo();
    }
}
