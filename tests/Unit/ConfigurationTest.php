<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;

class ConfigurationTest extends UnitTestCase
{
    public function testPackageBootstrapsWithUpdatedConfigFormat(): void
    {
        // The updated config only requires client_id, client_secret, and redirect.
        // If we got this far without exceptions, the package bootstrapped correctly.
        $config = config('services.sign_in_with_apple');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('client_id', $config);
        $this->assertArrayHasKey('client_secret', $config);
        $this->assertArrayHasKey('redirect', $config);
    }

    public function testConfigDoesNotRequireLoginKey(): void
    {
        // The `login` key is no longer required in the default config.
        $defaultConfig = include __DIR__ . '/../../config/services.php';

        $this->assertArrayNotHasKey(
            'login',
            $defaultConfig['sign_in_with_apple'],
            'The login key should not be in the default config file.'
        );
    }

    public function testExistingValidConfigStillWorks(): void
    {
        // Simulate a user who still has the old config keys set via env.
        // The package should still work — the old keys don't break anything.
        config([
            'services.sign_in_with_apple.login' => '/old-login-route',
            'services.sign_in_with_apple.redirect' => 'http://testing.dev/callback',
            'services.sign_in_with_apple.client_id' => 'test-client-id',
            'services.sign_in_with_apple.client_secret' => 'test-client-secret',
        ]);

        $config = config('services.sign_in_with_apple');

        $this->assertEquals('/old-login-route', $config['login']);
        $this->assertEquals('http://testing.dev/callback', $config['redirect']);
        $this->assertEquals('test-client-id', $config['client_id']);
        $this->assertEquals('test-client-secret', $config['client_secret']);
    }

    public function testDeprecatedLoginKeyTriggersDeprecationNotice(): void
    {
        config(['services.sign_in_with_apple.login' => '/old-login-route']);

        $deprecationTriggered = false;
        set_error_handler(function ($errno, $errstr) use (&$deprecationTriggered) {
            if ($errno === E_USER_DEPRECATED && str_contains($errstr, 'login')) {
                $deprecationTriggered = true;
            }
            return true;
        });

        $provider = new \GeneaLabs\LaravelSignInWithApple\Providers\ServiceProvider($this->app);
        $provider->boot();

        restore_error_handler();

        $this->assertTrue(
            $deprecationTriggered,
            'A deprecation notice should be triggered when the login key is present.'
        );
    }

    public function testNoDeprecationWhenLoginKeyAbsent(): void
    {
        config(['services.sign_in_with_apple.login' => null]);

        $deprecationTriggered = false;
        set_error_handler(function ($errno) use (&$deprecationTriggered) {
            if ($errno === E_USER_DEPRECATED) {
                $deprecationTriggered = true;
            }
            return true;
        });

        $provider = new \GeneaLabs\LaravelSignInWithApple\Providers\ServiceProvider($this->app);
        $provider->boot();

        restore_error_handler();

        $this->assertFalse(
            $deprecationTriggered,
            'No deprecation notice should be triggered when login key is absent.'
        );
    }
}
