<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use GeneaLabs\LaravelSignInWithApple\Http\Middleware\DisableCsrfForAppleCallback;
use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DisableCsrfMiddlewareTest extends UnitTestCase
{
    public function testMiddlewareAllowsPostRequestWithoutCsrfToken(): void
    {
        $middleware = new DisableCsrfForAppleCallback();

        $request = Request::create('/apple/callback', 'POST', [
            'code' => 'test-auth-code',
            'state' => 'test-state',
        ]);

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function testMiddlewarePassesRequestThrough(): void
    {
        $middleware = new DisableCsrfForAppleCallback();

        $request = Request::create('/apple/callback', 'POST', [
            'code' => 'auth-code-123',
        ]);

        $passedRequest = null;
        $middleware->handle($request, function ($req) use (&$passedRequest) {
            $passedRequest = $req;
            return new Response('OK');
        });

        $this->assertSame($request, $passedRequest);
        $this->assertEquals('auth-code-123', $passedRequest->input('code'));
    }

    public function testMiddlewareDoesNotAffectOtherRoutes(): void
    {
        $middleware = new DisableCsrfForAppleCallback();

        $request = Request::create('/other-route', 'POST');

        $response = $middleware->handle($request, function () {
            return new Response('Other route OK');
        });

        $this->assertEquals('Other route OK', $response->getContent());
    }
}
