<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Feature;

use Firebase\JWT\JWT;
use GeneaLabs\LaravelSignInWithApple\Events\AppleAccessRevoked;
use GeneaLabs\LaravelSignInWithApple\Http\Controllers\AppleNotificationController;
use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class RevocationIntegrationTest extends UnitTestCase
{
    private string $privateKey = '';
    private string $keyId = 'test-key-1';

    protected function setUp(): void
    {
        parent::setUp();

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $pem = '';
        openssl_pkey_export($resource, $pem);
        $this->privateKey = $pem;
        $details = openssl_pkey_get_details($resource);

        $n = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');
        $e = rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '=');

        $jwks = [
            'keys' => [[
                'kty' => 'RSA',
                'kid' => $this->keyId,
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => $n,
                'e' => $e,
            ]],
        ];

        Http::fake([
            'appleid.apple.com/auth/keys' => Http::response($jwks, 200),
        ]);

        Cache::forget('apple-auth-keys');

        // Register the notification route for integration testing
        Route::post('/apple/notifications', [AppleNotificationController::class, 'handle']);
    }

    protected function makeSignedJwt(array $claims): string
    {
        return JWT::encode($claims, $this->privateKey, 'RS256', $this->keyId);
    }

    public function testApplicationListenerReceivesRevocationEventWithCorrectUserData(): void
    {
        $receivedEvents = [];

        Event::listen(AppleAccessRevoked::class, function (AppleAccessRevoked $event) use (&$receivedEvents) {
            $receivedEvents[] = $event;
        });

        $jwt = $this->makeSignedJwt([
            'iss' => 'https://appleid.apple.com',
            'aud' => 'com.example.app',
            'iat' => time(),
            'events' => [
                'type' => 'consent-revoked',
                'sub' => '001234.abcdef1234567890.1234',
                'event_time' => 1700000000,
            ],
        ]);

        $response = $this->postJson('/apple/notifications', [
            'payload' => $jwt,
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        $this->assertCount(1, $receivedEvents);

        $event = $receivedEvents[0];
        $this->assertEquals('001234.abcdef1234567890.1234', $event->sub);
        $this->assertEquals('consent-revoked', $event->eventType);
        $this->assertEquals(1700000000, $event->payload['events']['event_time']);
    }

    public function testAccountDeleteListenerReceivesCorrectData(): void
    {
        $receivedEvents = [];

        Event::listen(AppleAccessRevoked::class, function (AppleAccessRevoked $event) use (&$receivedEvents) {
            $receivedEvents[] = $event;
        });

        $jwt = $this->makeSignedJwt([
            'iss' => 'https://appleid.apple.com',
            'aud' => 'com.example.app',
            'events' => [
                'type' => 'account-delete',
                'sub' => '001234.deleteduser.5678',
                'event_time' => 1700000001,
            ],
        ]);

        $response = $this->postJson('/apple/notifications', [
            'payload' => $jwt,
        ]);

        $response->assertOk();
        $this->assertCount(1, $receivedEvents);
        $this->assertEquals('account-delete', $receivedEvents[0]->eventType);
        $this->assertEquals('001234.deleteduser.5678', $receivedEvents[0]->sub);
    }

    public function testReAuthenticationAfterRevocationExposesEmailForUserMatching(): void
    {
        // Simulate what happens during re-authentication after revocation:
        // Apple provides a new sub but the same email. The provider should
        // expose the email and is_returning_user flag so the app can match
        // the existing user by email instead of creating a duplicate.
        $provider = new \GeneaLabs\LaravelSignInWithApple\Providers\SignInWithAppleProvider(
            $this->app->make('request'),
            'test-client-id',
            'test-client-secret',
            'http://localhost/callback'
        );

        // Simulate an ID token payload from Apple after revocation + re-auth.
        // Apple does NOT send the "user" POST param on re-auth (only on first auth).
        $idTokenPayload = [
            'sub' => 'new-apple-sub-after-revoke',
            'email' => 'user@example.com',
            'email_verified' => true,
            'aud' => 'test-client-id',
            'iss' => 'https://appleid.apple.com',
        ];

        // Encode as a JWT-like token (just the payload part for getUserByToken)
        $tokenPayload = rtrim(strtr(base64_encode(json_encode($idTokenPayload)), '+/', '-_'), '=');
        $fakeIdToken = "eyJhbGciOiJSUzI1NiJ9.{$tokenPayload}.fake-sig";

        // Use reflection to call protected getUserByToken
        $reflection = new \ReflectionMethod($provider, 'getUserByToken');
        $reflection->setAccessible(true);
        $userData = $reflection->invoke($provider, $fakeIdToken);

        $this->assertEquals('new-apple-sub-after-revoke', $userData['sub']);
        $this->assertEquals('user@example.com', $userData['email']);

        // Now test mapUserToObject — without the "user" POST param, it should
        // flag is_returning_user = true, indicating the app should match by email
        $mapReflection = new \ReflectionMethod($provider, 'mapUserToObject');
        $mapReflection->setAccessible(true);
        $user = $mapReflection->invoke($provider, $userData);

        $this->assertEquals('new-apple-sub-after-revoke', $user->getId());
        $this->assertEquals('user@example.com', $user->getEmail());
        $this->assertTrue($user['is_returning_user']);
        $this->assertNull($user->getName());
    }

    public function testFirstTimeAuthSetsIsReturningUserToFalse(): void
    {
        // Simulate first-time auth where Apple sends the "user" POST param
        $provider = new \GeneaLabs\LaravelSignInWithApple\Providers\SignInWithAppleProvider(
            $this->app->make('request'),
            'test-client-id',
            'test-client-secret',
            'http://localhost/callback'
        );

        // Set the "user" param on the request (Apple sends this on first auth)
        $this->app->make('request')->merge([
            'user' => json_encode([
                'name' => ['firstName' => 'John', 'lastName' => 'Doe'],
                'email' => 'john@example.com',
            ]),
        ]);

        $idTokenPayload = [
            'sub' => 'apple-sub-first-time',
            'email' => 'john@example.com',
        ];

        $mapReflection = new \ReflectionMethod($provider, 'mapUserToObject');
        $mapReflection->setAccessible(true);
        $user = $mapReflection->invoke($provider, $idTokenPayload);

        $this->assertEquals('apple-sub-first-time', $user->getId());
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertEquals('John Doe', $user->getName());
        $this->assertFalse($user['is_returning_user']);
    }
}
