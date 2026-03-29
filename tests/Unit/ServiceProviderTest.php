<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use GeneaLabs\LaravelSignInWithApple\Providers\ServiceProvider;
use GeneaLabs\LaravelSignInWithApple\Providers\SignInWithAppleProvider;
use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use Illuminate\Support\Facades\Blade;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;

class ServiceProviderTest extends UnitTestCase
{
    public function test_service_provider_is_registered(): void
    {
        $this->assertArrayHasKey(
            ServiceProvider::class,
            $this->app->getLoadedProviders(),
        );
    }

    public function test_config_is_merged(): void
    {
        $this->assertNotNull(config('services.sign_in_with_apple'));
        $this->assertArrayHasKey('login', config('services.sign_in_with_apple'));
        $this->assertArrayHasKey('redirect', config('services.sign_in_with_apple'));
        $this->assertArrayHasKey('client_id', config('services.sign_in_with_apple'));
        $this->assertArrayHasKey('client_secret', config('services.sign_in_with_apple'));
    }

    public function test_socialite_driver_is_registered(): void
    {
        $socialite = $this->app->make(SocialiteFactory::class);
        $driver = $socialite->driver('sign-in-with-apple');

        $this->assertInstanceOf(SignInWithAppleProvider::class, $driver);
    }

    public function test_blade_directive_is_registered(): void
    {
        $directives = Blade::getCustomDirectives();

        $this->assertArrayHasKey('signInWithApple', $directives);
    }
}
