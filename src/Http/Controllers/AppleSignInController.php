<?php

namespace GeneaLabs\LaravelSignInWithApple\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;

class AppleSignInController extends Controller
{
    /**
     * Redirect to Apple for authentication.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('sign-in-with-apple')
            ->scopes(['name', 'email'])
            ->redirect();
    }

    /**
     * Handle the callback from Apple.
     *
     * Override this controller or bind your own to customize user resolution.
     */
    public function callback(Request $request)
    {
        $user = Socialite::driver('sign-in-with-apple')->user();

        // Default: return the Socialite user.
        // Apps should override this controller or use their own route to persist users.
        return $user;
    }
}
