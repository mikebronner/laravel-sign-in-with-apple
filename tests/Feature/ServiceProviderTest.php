<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Feature;

use GeneaLabs\LaravelSignInWithApple\Providers\ServiceProvider;
use GeneaLabs\LaravelSignInWithApple\Providers\SignInWithAppleProvider;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\SocialiteServiceProvider;
use Orchestra\Testbench\TestCase;

class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SocialiteServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('services.sign_in_with_apple', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'redirect' => 'https://example.com/callback',
            'login' => '/login/apple',
        ]);
    }

    public function test_service_provider_is_registered(): void
    {
        $this->assertTrue(
            $this->app->providerIsLoaded(ServiceProvider::class),
            'ServiceProvider should be loaded'
        );
    }

    public function test_config_is_merged(): void
    {
        $config = config('services.sign_in_with_apple');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('client_id', $config);
        $this->assertArrayHasKey('client_secret', $config);
        $this->assertArrayHasKey('redirect', $config);
        $this->assertArrayHasKey('login', $config);
    }

    public function test_socialite_driver_is_registered(): void
    {
        $socialite = $this->app->make(Factory::class);
        $driver = $socialite->driver('sign-in-with-apple');

        $this->assertInstanceOf(SignInWithAppleProvider::class, $driver);
    }

    public function test_blade_directive_is_registered(): void
    {
        $directives = $this->app->make('blade.compiler')->getCustomDirectives();

        $this->assertArrayHasKey('signInWithApple', $directives);
    }
}
