<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Fixtures\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;

class SiwaController
{
    public function login()
    {
        return Socialite::driver("sign-in-with-apple")
            ->scopes(["name", "email"])
            ->redirect();
    }

    public function callback()
    {
        $user = Socialite::driver("sign-in-with-apple")
            ->user();
    }
}
