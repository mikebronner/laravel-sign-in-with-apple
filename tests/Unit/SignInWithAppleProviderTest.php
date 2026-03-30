<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use GeneaLabs\LaravelSignInWithApple\Providers\SignInWithAppleProvider;
use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\User;
use Mockery;
use ReflectionMethod;

class SignInWithAppleProviderTest extends UnitTestCase
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

    public function testGetAuthUrlReturnsAppleUrl(): void
    {
        $provider = $this->makeProvider();
        $provider->stateless();

        $url = $provider->redirect()->getTargetUrl();

        $this->assertStringContainsString('appleid.apple.com/auth/authorize', $url);
        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('redirect_uri=', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('response_mode=form_post', $url);
    }

    public function testGetTokenUrlReturnsAppleTokenEndpoint(): void
    {
        $provider = $this->makeProvider();

        $method = new ReflectionMethod($provider, 'getTokenUrl');
        $method->setAccessible(true);

        $this->assertEquals(
            'https://appleid.apple.com/auth/token',
            $method->invoke($provider)
        );
    }

    public function testGetTokenFieldsIncludesGrantType(): void
    {
        $provider = $this->makeProvider();

        $method = new ReflectionMethod($provider, 'getTokenFields');
        $method->setAccessible(true);

        $fields = $method->invoke($provider, 'test-code');

        $this->assertArrayHasKey('grant_type', $fields);
        $this->assertEquals('authorization_code', $fields['grant_type']);
        $this->assertArrayHasKey('code', $fields);
        $this->assertEquals('test-code', $fields['code']);
        $this->assertArrayHasKey('client_id', $fields);
        $this->assertEquals('test-client-id', $fields['client_id']);
        $this->assertArrayHasKey('client_secret', $fields);
        $this->assertEquals('test-client-secret', $fields['client_secret']);
    }

    public function testGetUserByTokenDecodesJwtClaims(): void
    {
        $provider = $this->makeProvider();

        $claims = [
            'sub' => 'apple-user-123',
            'email' => 'test@privaterelay.appleid.com',
            'email_verified' => true,
        ];

        $header = base64_encode(json_encode(['alg' => 'RS256']));
        $payload = base64_encode(json_encode($claims));
        $signature = base64_encode('fake-signature');
        $token = "{$header}.{$payload}.{$signature}";

        $method = new ReflectionMethod($provider, 'getUserByToken');
        $method->setAccessible(true);

        $result = $method->invoke($provider, $token);

        $this->assertEquals('apple-user-123', $result['sub']);
        $this->assertEquals('test@privaterelay.appleid.com', $result['email']);
        $this->assertTrue($result['email_verified']);
    }

    public function testMapUserToObjectSetsCorrectFields(): void
    {
        $provider = $this->makeProvider();

        $method = new ReflectionMethod($provider, 'mapUserToObject');
        $method->setAccessible(true);

        $user = $method->invoke($provider, [
            'sub' => 'apple-user-456',
            'email' => 'user@example.com',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('apple-user-456', $user->getId());
        $this->assertEquals('user@example.com', $user->getEmail());
        $this->assertNull($user->getName());
    }

    public function testMapUserToObjectWithNameFromRequest(): void
    {
        // mapUserToObject uses request() helper, so we must set data on the app request
        $this->app['request']->merge([
            'user' => json_encode([
                'name' => [
                    'firstName' => 'John',
                    'lastName' => 'Doe',
                ],
            ]),
        ]);

        $provider = $this->makeProvider();

        $method = new ReflectionMethod($provider, 'mapUserToObject');
        $method->setAccessible(true);

        $user = $method->invoke($provider, [
            'sub' => 'apple-user-789',
            'email' => 'john@example.com',
        ]);

        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertEquals('apple-user-789', $user->getId());
    }

    public function testMapUserToObjectWithPartialName(): void
    {
        $this->app['request']->merge([
            'user' => json_encode([
                'name' => [
                    'firstName' => 'Jane',
                ],
            ]),
        ]);

        $provider = $this->makeProvider();

        $method = new ReflectionMethod($provider, 'mapUserToObject');
        $method->setAccessible(true);

        $user = $method->invoke($provider, [
            'sub' => 'apple-user-101',
            'email' => 'jane@example.com',
        ]);

        $this->assertEquals('Jane', $user->getName());
    }

    public function testMapUserToObjectWithoutEmail(): void
    {
        $provider = $this->makeProvider();

        $method = new ReflectionMethod($provider, 'mapUserToObject');
        $method->setAccessible(true);

        $user = $method->invoke($provider, [
            'sub' => 'apple-user-no-email',
        ]);

        $this->assertEquals('apple-user-no-email', $user->getId());
        $this->assertNull($user->getEmail());
        $this->assertNull($user->getName());
    }

    public function testCodeFieldsIncludeResponseModeFormPost(): void
    {
        $provider = $this->makeProvider();

        $method = new ReflectionMethod($provider, 'getCodeFields');
        $method->setAccessible(true);

        $fields = $method->invoke($provider, 'test-state');

        $this->assertEquals('form_post', $fields['response_mode']);
        $this->assertEquals('code', $fields['response_type']);
        $this->assertEquals('test-client-id', $fields['client_id']);
        $this->assertEquals('test-state', $fields['state']);
    }
}
