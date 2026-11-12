<?php

namespace App\Http\Controllers;

use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Try the demo": signs in as the seeded demo user, so reviewers do not
 * need to register. Only that one account, and only when enabled.
 */
class DemoLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(config('demo_shop.demo_login'), 404);

        $user = User::query()->where('email', DemoDataSeeder::EMAIL)->first() ?? abort(404);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
