<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Feature;

use GeneaLabs\LaravelSignInWithApple\Providers\ServiceProvider;
use GeneaLabs\LaravelSignInWithApple\Providers\SignInWithAppleProvider;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\SocialiteServiceProvider;
use Orchestra\Testbench\TestCase;

class SignInWithAppleProviderTest extends TestCase
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
            'client_id' => 'com.example.service',
            'client_secret' => 'test-secret',
            'redirect' => 'https://example.com/callback',
            'login' => '/login/apple',
        ]);
    }

    private function getDriver(): SignInWithAppleProvider
    {
        $socialite = $this->app->make(Factory::class);

        return $socialite->driver('sign-in-with-apple')->stateless();
    }

    public function test_redirect_url_contains_apple_auth(): void
    {
        $response = $this->getDriver()->redirect();

        $this->assertStringContainsString(
            'https://appleid.apple.com/auth/authorize',
            $response->getTargetUrl()
        );
    }

    public function test_redirect_url_contains_client_id(): void
    {
        $response = $this->getDriver()->redirect();

        $this->assertStringContainsString(
            'client_id=com.example.service',
            $response->getTargetUrl()
        );
    }

    public function test_redirect_uses_form_post_response_mode(): void
    {
        $response = $this->getDriver()->redirect();

        $this->assertStringContainsString(
            'response_mode=form_post',
            $response->getTargetUrl()
        );
    }

    public function test_redirect_uses_code_response_type(): void
    {
        $response = $this->getDriver()->redirect();

        $this->assertStringContainsString(
            'response_type=code',
            $response->getTargetUrl()
        );
    }

    public function test_redirect_contains_redirect_uri(): void
    {
        $response = $this->getDriver()->redirect();

        $this->assertStringContainsString(
            'redirect_uri=',
            $response->getTargetUrl()
        );
    }

    public function test_stateless_redirect_omits_state(): void
    {
        $response = $this->getDriver()->redirect();

        $this->assertStringNotContainsString(
            'state=',
            $response->getTargetUrl()
        );
    }
}
