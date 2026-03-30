<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use GeneaLabs\LaravelSignInWithApple\Exceptions\InvalidAppleCredentialsException;
use GeneaLabs\LaravelSignInWithApple\Providers\ServiceProvider;
use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use Laravel\Socialite\Facades\Socialite;
use ReflectionMethod;

class InvalidCredentialsTest extends UnitTestCase
{
    public function testValidationThrowsWhenConfigIsMissing(): void
    {
        $provider = $this->app->getProvider(ServiceProvider::class);
        $method = new ReflectionMethod($provider, 'validateAppleConfig');
        $method->setAccessible(true);

        $this->expectException(InvalidAppleCredentialsException::class);
        $this->expectExceptionMessage('configuration is missing');

        $method->invoke($provider, null);
    }

    public function testValidationThrowsWhenConfigIsEmpty(): void
    {
        $provider = $this->app->getProvider(ServiceProvider::class);
        $method = new ReflectionMethod($provider, 'validateAppleConfig');
        $method->setAccessible(true);

        $this->expectException(InvalidAppleCredentialsException::class);
        $this->expectExceptionMessage('configuration is missing');

        $method->invoke($provider, []);
    }

    public function testValidationThrowsWhenClientIdIsMissing(): void
    {
        $provider = $this->app->getProvider(ServiceProvider::class);
        $method = new ReflectionMethod($provider, 'validateAppleConfig');
        $method->setAccessible(true);

        $this->expectException(InvalidAppleCredentialsException::class);
        $this->expectExceptionMessage('client_id');

        $method->invoke($provider, [
            'client_id' => '',
            'client_secret' => 'some-secret',
            'redirect' => 'http://example.com/callback',
        ]);
    }

    public function testValidationThrowsWhenClientSecretIsMissing(): void
    {
        $provider = $this->app->getProvider(ServiceProvider::class);
        $method = new ReflectionMethod($provider, 'validateAppleConfig');
        $method->setAccessible(true);

        $this->expectException(InvalidAppleCredentialsException::class);
        $this->expectExceptionMessage('client_secret');

        $method->invoke($provider, [
            'client_id' => 'some-id',
            'client_secret' => '',
            'redirect' => 'http://example.com/callback',
        ]);
    }

    public function testValidationPassesWithValidConfig(): void
    {
        $provider = $this->app->getProvider(ServiceProvider::class);
        $method = new ReflectionMethod($provider, 'validateAppleConfig');
        $method->setAccessible(true);

        // Should not throw
        $method->invoke($provider, [
            'client_id' => 'com.example.app',
            'client_secret' => 'valid-secret',
            'redirect' => 'http://example.com/callback',
        ]);

        $this->assertTrue(true);
    }

    public function testExceptionIncludesContext(): void
    {
        $exception = new InvalidAppleCredentialsException(
            'Test error',
            ['apple_error' => 'invalid_client', 'client_id' => 'test-id'],
        );

        $this->assertEquals('Test error', $exception->getMessage());
        $this->assertEquals('invalid_client', $exception->getContext()['apple_error']);
        $this->assertEquals('test-id', $exception->getContext()['client_id']);
    }

    public function testDriverResolutionValidatesConfig(): void
    {
        // Clear the config to trigger validation failure
        config()->set('services.apple.sign_in', [
            'client_id' => '',
            'client_secret' => '',
            'redirect' => '',
            'login' => '',
        ]);

        $this->expectException(InvalidAppleCredentialsException::class);

        Socialite::driver('sign-in-with-apple');
    }
}
