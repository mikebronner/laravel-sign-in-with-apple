<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use GeneaLabs\LaravelSignInWithApple\Events\AppleAccessRevoked;
use GeneaLabs\LaravelSignInWithApple\Http\Controllers\AppleNotificationController;
use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

class RevocationHandlingTest extends UnitTestCase
{
    protected function makeJwt(array $claims): string
    {
        $header = base64_encode(json_encode(['alg' => 'ES256']));
        $payload = base64_encode(json_encode($claims));
        $signature = base64_encode('fake-sig');

        return "{$header}.{$payload}.{$signature}";
    }

    public function testRevocationEventIsDispatched(): void
    {
        Event::fake();

        $jwt = $this->makeJwt([
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

        $jwt = $this->makeJwt([
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

        $jwt = $this->makeJwt([
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

    public function testMissingSubjectReturns400(): void
    {
        $jwt = $this->makeJwt([
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

        $jwt = $this->makeJwt($claims);

        $request = Request::create('/apple/notifications', 'POST', [
            'payload' => $jwt,
        ]);

        $controller = new AppleNotificationController();
        $controller->handle($request);

        Event::assertDispatched(AppleAccessRevoked::class, function ($event) use ($claims) {
            return $event->payload === $claims;
        });
    }
}
