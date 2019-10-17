<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Browser;

use GeneaLabs\LaravelSignInWithApple\Tests\BrowserTestCase;
use Laravel\Dusk\Browser;

class SignInWithAppleTest extends BrowserTestCase
{
    public function testButtonIsRendered()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visit("/tests")
                ->assertSee("Sign in with Apple");
        });
    }

    public function testRedirectShowsAppleIdLogin()
    {
        $this->browse(function (Browser $browser) {
            $browser
                ->visit("/tests")
                ->click('#sign-in-with-apple')
                ->assertSee("Use your Apple ID to sign in to Test Service ID.");
        });
    }
}
