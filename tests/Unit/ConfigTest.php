<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;

class ConfigTest extends UnitTestCase
{
    public function testNewConfigKeysAreUsed(): void
    {
        $this->assertNotNull(config('services.apple.sign_in'));
        $this->assertEquals('https://testing.dev/siwa-callback', config('services.apple.sign_in.redirect'));
        $this->assertEquals('add-your-own', config('services.apple.sign_in.client_id'));
        $this->assertEquals('add-your-own', config('services.apple.sign_in.client_secret'));
    }

    public function testSocialiteDriverWorksWithNewConfigKeys(): void
    {
        $driver = \Laravel\Socialite\Facades\Socialite::driver('sign-in-with-apple');

        $this->assertInstanceOf(
            \GeneaLabs\LaravelSignInWithApple\Providers\SignInWithAppleProvider::class,
            $driver
        );
    }

    public function testDeprecatedConfigKeyTriggersWarning(): void
    {
        // Clear the new key and set only the old key
        $services = config('services');
        unset($services['apple']['sign_in']);
        $services['sign_in_with_apple'] = [
            'redirect' => 'http://old.dev/callback',
            'client_id' => 'old-client-id',
            'client_secret' => 'old-client-secret',
        ];
        config()->set('services', $services);

        $deprecationTriggered = false;
        set_error_handler(function ($errno, $errstr) use (&$deprecationTriggered) {
            if ($errno === E_USER_DEPRECATED && str_contains($errstr, 'services.sign_in_with_apple')) {
                $deprecationTriggered = true;
            }
            return true;
        });

        // Re-run the migration logic
        $provider = $this->app->getProvider(\GeneaLabs\LaravelSignInWithApple\Providers\ServiceProvider::class);
        $reflection = new \ReflectionMethod($provider, 'migrateDeprecatedConfig');
        $reflection->setAccessible(true);
        $reflection->invoke($provider);

        restore_error_handler();

        $this->assertTrue($deprecationTriggered, 'Deprecation warning should be triggered for old config key');
        $this->assertEquals('http://old.dev/callback', config('services.apple.sign_in.redirect'));
    }

    public function testDeprecatedConfigKeyDoesNotTriggerWarningWhenNewKeyExists(): void
    {
        // Both keys exist — new key takes precedence, no deprecation
        config()->set('services.apple.sign_in', [
            'redirect' => 'http://new.dev/callback',
            'client_id' => 'new-client-id',
            'client_secret' => 'new-client-secret',
        ]);
        config()->set('services.sign_in_with_apple', [
            'redirect' => 'http://old.dev/callback',
        ]);

        $deprecationTriggered = false;
        set_error_handler(function ($errno) use (&$deprecationTriggered) {
            if ($errno === E_USER_DEPRECATED) {
                $deprecationTriggered = true;
            }
            return true;
        });

        $provider = $this->app->getProvider(\GeneaLabs\LaravelSignInWithApple\Providers\ServiceProvider::class);
        $reflection = new \ReflectionMethod($provider, 'migrateDeprecatedConfig');
        $reflection->setAccessible(true);
        $reflection->invoke($provider);

        restore_error_handler();

        $this->assertFalse($deprecationTriggered, 'No deprecation when new key exists');
        $this->assertEquals('http://new.dev/callback', config('services.apple.sign_in.redirect'));
    }
}
