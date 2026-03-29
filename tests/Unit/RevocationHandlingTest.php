<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use Firebase\JWT\JWT;
use GeneaLabs\LaravelSignInWithApple\Events\AppleAccessRevoked;
use GeneaLabs\LaravelSignInWithApple\Http\Controllers\AppleNotificationController;
use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class RevocationHandlingTest extends UnitTestCase
{
    private string $privateKey = '';
    private string $publicKey = '';
    private string $keyId = 'test-key-1';

    protected function setUp(): void
    {
        parent::setUp();

        // Generate an RSA key pair for testing JWT signing/verification
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $pem = '';
        openssl_pkey_export($resource, $pem);
        $this->privateKey = $pem;
        $details = openssl_pkey_get_details($resource);
        $this->publicKey = $details['key'];

        // Mock Apple's public keys endpoint with a JWKS response
        $n = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');
        $e = rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '=');

        $jwks = [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => $this->keyId,
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'n' => $n,
                    'e' => $e,
                ],
            ],
        ];

        Http::fake([
            'appleid.apple.com/auth/keys' => Http::response($jwks, 200),
        ]);

        // Clear cached keys between tests
        Cache::forget('apple-auth-keys');
    }

    protected function makeSignedJwt(array $claims): string
    {
        return JWT::encode($claims, $this->privateKey, 'RS256', $this->keyId);
    }

    public function testRevocationEventIsDispatched(): void
    {
        Event::fake();

        $jwt = $this->makeSignedJwt([
            'iss' => 'https://appleid.apple.com',
            'aud' => 'com.example.app',
            'events' => [
                'type' => 'consent-revoked',
                'sub' => 'apple-user-123',
            ],
        ]);

        $request = Request::create('/apple/notifications', 'POST', [
            'payload' => $jwt,
        ]);

        $controller = new AppleNotificationController();
        $response = $controller->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        Event::assertDispatched(AppleAccessRevoked::class, function ($event) {
            return $event->sub === 'apple-user-123'
                && $event->eventType === 'consent-revoked';
        });
    }

    public function testAccountDeleteEventIsDispatched(): void
    {
        Event::fake();

        $jwt = $this->makeSignedJwt([
            'events' => [
                'type' => 'account-delete',
                'sub' => 'apple-user-456',
            ],
        ]);

        $request = Request::create('/apple/notifications', 'POST', [
            'payload' => $jwt,
        ]);

        $controller = new AppleNotificationController();
        $response = $controller->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        Event::assertDispatched(AppleAccessRevoked::class, function ($event) {
            return $event->sub === 'apple-user-456'
                && $event->eventType === 'account-delete';
        });
    }

    public function testNonRevocationEventDoesNotDispatch(): void
    {
        Event::fake();

        $jwt = $this->makeSignedJwt([
            'events' => [
                'type' => 'email-enabled',
                'sub' => 'apple-user-789',
            ],
        ]);

        $request = Request::create('/apple/notifications', 'POST', [
            'payload' => $jwt,
        ]);

        $controller = new AppleNotificationController();
        $response = $controller->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        Event::assertNotDispatched(AppleAccessRevoked::class);
    }

    public function testMissingPayloadReturns400(): void
    {
        $request = Request::create('/apple/notifications', 'POST');

        $controller = new AppleNotificationController();
        $response = $controller->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testInvalidPayloadReturns400(): void
    {
        $request = Request::create('/apple/notifications', 'POST', [
            'payload' => 'not-a-jwt',
        ]);

        $controller = new AppleNotificationController();
        $response = $controller->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testUnsignedJwtIsRejected(): void
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'none'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode([
            'events' => ['type' => 'consent-revoked', 'sub' => 'attacker-123'],
        ])), '+/', '-_'), '=');
        $fakeJwt = "{$header}.{$payload}.";

        $request = Request::create('/apple/notifications', 'POST', [
            'payload' => $fakeJwt,
        ]);

        Event::fake();
        $controller = new AppleNotificationController();
        $response = $controller->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        Event::assertNotDispatched(AppleAccessRevoked::class);
    }

    public function testForgedJwtWithFakeSignatureIsRejected(): void
    {
        // Create a JWT signed with a different key
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $wrongKey = '';
        openssl_pkey_export($resource, $wrongKey);

        $forgedJwt = JWT::encode([
            'events' => ['type' => 'consent-revoked', 'sub' => 'forged-user'],
        ], $wrongKey, 'RS256', $this->keyId);

        $request = Request::create('/apple/notifications', 'POST', [
            'payload' => $forgedJwt,
        ]);

        Event::fake();
        $controller = new AppleNotificationController();
        $response = $controller->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        Event::assertNotDispatched(AppleAccessRevoked::class);
    }

    public function testMissingSubjectReturns400(): void
    {
        $jwt = $this->makeSignedJwt([
            'events' => [
                'type' => 'consent-revoked',
            ],
        ]);

        $request = Request::create('/apple/notifications', 'POST', [
            'payload' => $jwt,
        ]);

        $controller = new AppleNotificationController();
        $response = $controller->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testEventContainsFullPayload(): void
    {
        Event::fake();

        $claims = [
            'iss' => 'https://appleid.apple.com',
            'aud' => 'com.example.app',
            'iat' => time(),
            'events' => [
                'type' => 'consent-revoked',
                'sub' => 'apple-user-full',
                'event_time' => 1234567890,
            ],
        ];

        $jwt = $this->makeSignedJwt($claims);

        $request = Request::create('/apple/notifications', 'POST', [
            'payload' => $jwt,
        ]);

        $controller = new AppleNotificationController();
        $controller->handle($request);

        Event::assertDispatched(AppleAccessRevoked::class, function ($event) use ($claims) {
            return $event->sub === 'apple-user-full'
                && $event->eventType === 'consent-revoked'
                && $event->payload['events']['event_time'] === 1234567890;
        });
    }

    public function testApplePublicKeysAreCached(): void
    {
        Http::fake([
            'appleid.apple.com/auth/keys*' => Http::sequence()
                ->push($this->getJwksResponse(), 200)
                ->push([], 500), // Second call would fail
        ]);

        Cache::forget('apple-auth-keys');

        $jwt = $this->makeSignedJwt([
            'events' => ['type' => 'consent-revoked', 'sub' => 'user-1'],
        ]);

        $controller = new AppleNotificationController();

        // First call — fetches keys
        $request1 = Request::create('/apple/notifications', 'POST', ['payload' => $jwt]);
        $response1 = $controller->handle($request1);
        $this->assertEquals(200, $response1->getStatusCode());

        // Second call — uses cached keys (would fail if not cached)
        $request2 = Request::create('/apple/notifications', 'POST', ['payload' => $jwt]);
        $response2 = $controller->handle($request2);
        $this->assertEquals(200, $response2->getStatusCode());
    }

    private function getJwksResponse(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $details = openssl_pkey_get_details($resource);

        $n = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');
        $e = rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '=');

        return [
            'keys' => [[
                'kty' => 'RSA',
                'kid' => $this->keyId,
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => $n,
                'e' => $e,
            ]],
        ];
    }
}
