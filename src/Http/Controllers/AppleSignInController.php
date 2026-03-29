<?php

namespace GeneaLabs\LaravelSignInWithApple\Http\Controllers;

use GeneaLabs\LaravelSignInWithApple\Events\AppleSignInCallback;
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
     * Dispatches an AppleSignInCallback event with the Socialite user,
     * then redirects to a configurable route. Listen for the event
     * to persist or process the authenticated user.
     */
    public function callback(Request $request): RedirectResponse
    {
        $user = Socialite::driver('sign-in-with-apple')->user();

        AppleSignInCallback::dispatch($user);

        $redirect = config(
            'services.sign_in_with_apple.routes.callback_redirect',
            '/',
        );

        return redirect()->to($redirect);
    }
}
