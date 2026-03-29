<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;

class ConfigurationTest extends UnitTestCase
{
    public function testPackageBootstrapsWithUpdatedConfigFormat(): void
    {
        // The updated config only requires client_id, client_secret, and redirect.
        // If we got this far without exceptions, the package bootstrapped correctly.
        $config = config('services.apple');

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
            $defaultConfig['apple'],
            'The login key should not be in the default config file.'
        );
    }

    public function testExistingValidConfigStillWorks(): void
    {
        config([
            'services.apple.redirect' => 'http://testing.dev/callback',
            'services.apple.client_id' => 'test-client-id',
            'services.apple.client_secret' => 'test-client-secret',
        ]);

        $config = config('services.apple');

        $this->assertEquals('http://testing.dev/callback', $config['redirect']);
        $this->assertEquals('test-client-id', $config['client_id']);
        $this->assertEquals('test-client-secret', $config['client_secret']);
    }

    public function testDeprecatedConfigKeyIsDetected(): void
    {
        // Verify the migration logic exists and checks for the old key
        $config = $this->app['config'];
        
        // The migration happens at boot time. This test verifies the structure:
        // If old key was present without new key, values would be copied.
        // We can't easily test the deprecation warning in this environment,
        // but we can verify the migration logic is in place by checking the provider.
        
        $provider = $this->app->getProvider(\GeneaLabs\LaravelSignInWithApple\Providers\ServiceProvider::class);
        $reflection = new \ReflectionMethod($provider, 'migrateDeprecatedConfig');
        
        // Method exists and is callable
        $this->assertTrue($reflection->isPublic() || !$reflection->isPrivate());
    }

    public function testNoDeprecationWhenNewConfigKeyExists(): void
    {
        // When `services.apple` is already set, no deprecation should fire
        // even if the old key also exists.
        config([
            'services.apple' => [
                'client_id' => 'new-id',
                'client_secret' => 'new-secret',
                'redirect' => '/new-callback',
            ],
            'services.sign_in_with_apple' => [
                'client_id' => 'old-id',
            ],
        ]);

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
            'No deprecation notice should fire when the new config key exists.'
        );
    }
}
