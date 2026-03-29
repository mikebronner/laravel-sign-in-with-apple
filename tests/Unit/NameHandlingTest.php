<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use GeneaLabs\LaravelSignInWithApple\Providers\SignInWithAppleProvider;
use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use Illuminate\Http\Request;
use ReflectionMethod;

class NameHandlingTest extends UnitTestCase
{
    protected function makeProvider(?Request $request = null): SignInWithAppleProvider
    {
        $request = $request ?? Request::create('/');

        return new SignInWithAppleProvider(
            $request,
            'test-client-id',
            'test-client-secret',
            'https://example.com/callback'
        );
    }

    protected function callMapUser(SignInWithAppleProvider $provider, array $user)
    {
        $method = new ReflectionMethod($provider, 'mapUserToObject');
        $method->setAccessible(true);

        return $method->invoke($provider, $user);
    }

    public function testFirstSignInWithFullName(): void
    {
        $this->app['request']->merge([
            'user' => json_encode([
                'name' => ['firstName' => 'John', 'lastName' => 'Doe'],
            ]),
        ]);

        $provider = $this->makeProvider();
        $user = $this->callMapUser($provider, ['sub' => 'apple-123', 'email' => 'john@example.com']);

        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('John', $user->first_name);
        $this->assertEquals('Doe', $user->last_name);
        $this->assertEquals('john@example.com', $user->getEmail());
    }

    public function testFirstSignInWithFirstNameOnly(): void
    {
        $this->app['request']->merge([
            'user' => json_encode([
                'name' => ['firstName' => 'Jane'],
            ]),
        ]);

        $provider = $this->makeProvider();
        $user = $this->callMapUser($provider, ['sub' => 'apple-456']);

        $this->assertEquals('Jane', $user->getName());
        $this->assertEquals('Jane', $user->first_name);
        $this->assertNull($user->last_name);
    }

    public function testFirstSignInWithLastNameOnly(): void
    {
        $this->app['request']->merge([
            'user' => json_encode([
                'name' => ['lastName' => 'Smith'],
            ]),
        ]);

        $provider = $this->makeProvider();
        $user = $this->callMapUser($provider, ['sub' => 'apple-789']);

        $this->assertEquals('Smith', $user->getName());
        $this->assertNull($user->first_name);
        $this->assertEquals('Smith', $user->last_name);
    }

    public function testSubsequentSignInWithoutName(): void
    {
        // Apple only sends name on first authorization
        $provider = $this->makeProvider();
        $user = $this->callMapUser($provider, ['sub' => 'apple-123', 'email' => 'john@example.com']);

        $this->assertNull($user->getName());
        $this->assertNull($user->first_name);
        $this->assertNull($user->last_name);
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertEquals('apple-123', $user->getId());
    }

    public function testEmptyUserJsonDoesNotError(): void
    {
        $this->app['request']->merge([
            'user' => json_encode([]),
        ]);

        $provider = $this->makeProvider();
        $user = $this->callMapUser($provider, ['sub' => 'apple-000']);

        $this->assertNull($user->getName());
        $this->assertNull($user->first_name);
        $this->assertNull($user->last_name);
    }

    public function testInvalidUserJsonDoesNotError(): void
    {
        $this->app['request']->merge([
            'user' => 'not-valid-json',
        ]);

        $provider = $this->makeProvider();
        $user = $this->callMapUser($provider, ['sub' => 'apple-bad']);

        $this->assertNull($user->getName());
        $this->assertNull($user->first_name);
        $this->assertNull($user->last_name);
    }

    public function testNameFieldsAreInRawData(): void
    {
        $this->app['request']->merge([
            'user' => json_encode([
                'name' => ['firstName' => 'Test', 'lastName' => 'User'],
            ]),
        ]);

        $provider = $this->makeProvider();
        $user = $this->callMapUser($provider, ['sub' => 'apple-raw', 'email' => 'test@example.com']);

        $raw = $user->getRaw();
        $this->assertEquals('Test', $raw['name']['firstName']);
        $this->assertEquals('User', $raw['name']['lastName']);
    }

    public function testUserFromTokenWithName(): void
    {
        $claims = ['sub' => 'apple-token', 'email' => 'token@example.com'];
        $header = base64_encode(json_encode(['alg' => 'RS256']));
        $payload = base64_encode(json_encode($claims));
        $signature = base64_encode('fake');
        $idToken = "{$header}.{$payload}.{$signature}";

        $this->app['request']->merge([
            'user' => json_encode([
                'name' => ['firstName' => 'Token', 'lastName' => 'User'],
            ]),
        ]);

        $user = \Laravel\Socialite\Facades\Socialite::driver('sign-in-with-apple')
            ->userFromToken($idToken);

        $this->assertEquals('Token User', $user->getName());
        $this->assertEquals('Token', $user->first_name);
        $this->assertEquals('User', $user->last_name);
    }
}
