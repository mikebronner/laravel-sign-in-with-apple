<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Feature;

use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use Laravel\Socialite\Facades\Socialite;

class SignInFlowTest extends UnitTestCase
{
    public function testRedirectSendsToApple(): void
    {
        $response = Socialite::driver('sign-in-with-apple')
            ->scopes(['name', 'email'])
            ->stateless()
            ->redirect();

        $this->assertEquals(302, $response->getStatusCode());

        $targetUrl = $response->getTargetUrl();
        $this->assertStringContainsString('appleid.apple.com/auth/authorize', $targetUrl);
        $this->assertStringContainsString('scope=name%20email', $targetUrl);
        $this->assertStringContainsString('response_mode=form_post', $targetUrl);
    }

    public function testRedirectIncludesClientId(): void
    {
        $response = Socialite::driver('sign-in-with-apple')
            ->stateless()
            ->redirect();

        $targetUrl = $response->getTargetUrl();
        $this->assertStringContainsString('client_id=add-your-own', $targetUrl);
    }

    public function testRedirectIncludesCallbackUrl(): void
    {
        $response = Socialite::driver('sign-in-with-apple')
            ->stateless()
            ->redirect();

        $targetUrl = $response->getTargetUrl();
        $this->assertStringContainsString('redirect_uri=', $targetUrl);
        $this->assertStringContainsString(urlencode('http://testing.dev/siwa-callback'), $targetUrl);
    }

    public function testCallbackWithMockedTokenResponse(): void
    {
        $claims = [
            'sub' => 'apple-user-test',
            'email' => 'test@privaterelay.appleid.com',
            'email_verified' => 'true',
        ];

        $header = base64_encode(json_encode(['alg' => 'RS256', 'kid' => 'test']));
        $payload = base64_encode(json_encode($claims));
        $signature = base64_encode('fake');
        $idToken = "{$header}.{$payload}.{$signature}";

        $abstractUser = Socialite::driver('sign-in-with-apple')
            ->userFromToken($idToken);

        // userFromToken calls getUserByToken which decodes the JWT payload
        $this->assertEquals('apple-user-test', $abstractUser->getId());
        $this->assertEquals('test@privaterelay.appleid.com', $abstractUser->getEmail());
    }

    public function testCallbackWithNameInRequest(): void
    {
        $claims = [
            'sub' => 'apple-user-named',
            'email' => 'named@example.com',
        ];

        $header = base64_encode(json_encode(['alg' => 'RS256']));
        $payload = base64_encode(json_encode($claims));
        $signature = base64_encode('fake');
        $idToken = "{$header}.{$payload}.{$signature}";

        // Simulate Apple sending user data in the request
        $this->app['request']->merge([
            'user' => json_encode([
                'name' => [
                    'firstName' => 'Test',
                    'lastName' => 'User',
                ],
            ]),
        ]);

        $abstractUser = Socialite::driver('sign-in-with-apple')
            ->userFromToken($idToken);

        $this->assertEquals('apple-user-named', $abstractUser->getId());
        $this->assertEquals('named@example.com', $abstractUser->getEmail());
        $this->assertEquals('Test User', $abstractUser->getName());
    }

    public function testCallbackWithHiddenEmail(): void
    {
        $claims = [
            'sub' => 'apple-user-hidden',
            'is_private_email' => 'true',
        ];

        $header = base64_encode(json_encode(['alg' => 'RS256']));
        $payload = base64_encode(json_encode($claims));
        $signature = base64_encode('fake');
        $idToken = "{$header}.{$payload}.{$signature}";

        $abstractUser = Socialite::driver('sign-in-with-apple')
            ->userFromToken($idToken);

        $this->assertEquals('apple-user-hidden', $abstractUser->getId());
        $this->assertNull($abstractUser->getEmail());
    }
}
