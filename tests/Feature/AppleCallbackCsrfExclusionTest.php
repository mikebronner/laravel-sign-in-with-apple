<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Feature;

use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class AppleCallbackCsrfExclusionTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->registerTestRoutes();
    }

    /**
     * Test that the Apple callback route is excluded from CSRF verification.
     *
     * This ensures that POST requests from Apple's servers (which don't include
     * CSRF tokens) are accepted without triggering a 419 error.
     */
    public function testAppleCallbackPostRequestAcceptedWithoutCsrfToken(): void
    {
        $response = $this->post('/apple/callback', [
            'code' => 'test-auth-code',
            'state' => 'test-state',
            'id_token' => 'test-id-token',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('callback received', $response->getContent());
    }

    /**
     * Test that the Apple callback route is in the CSRF exclusion list.
     *
     * The ServiceProvider calls VerifyCsrfToken::except() with the callback
     * path parsed from the configured redirect URI. This verifies the path
     * was actually registered as an exception.
     */
    public function testAppleCallbackPathIsInCsrfExclusionList(): void
    {
        $middleware = $this->app->make(VerifyCsrfToken::class);

        // The configured redirect URI is http://testing.dev/siwa-callback
        // so the excluded path should be /siwa-callback
        $callbackRequest = Request::create('/siwa-callback', 'POST');
        $protectedRequest = Request::create('/protected', 'POST');

        // Use reflection to access the protected isReading + inExceptArray check
        $reflection = new \ReflectionClass($middleware);
        $exceptMethod = $reflection->getMethod('inExceptArray');
        $exceptMethod->setAccessible(true);

        $this->assertTrue(
            $exceptMethod->invoke($middleware, $callbackRequest),
            'Apple callback path should be in the CSRF exclusion list'
        );

        $this->assertFalse(
            $exceptMethod->invoke($middleware, $protectedRequest),
            'Non-Apple routes should NOT be in the CSRF exclusion list'
        );
    }

    /**
     * Test that other routes are not affected by the CSRF exclusion.
     *
     * Verifies that only the Apple callback path is excluded and arbitrary
     * routes remain subject to CSRF verification.
     */
    public function testOtherRoutesNotInCsrfExclusionList(): void
    {
        $middleware = $this->app->make(VerifyCsrfToken::class);
        $reflection = new \ReflectionClass($middleware);
        $exceptMethod = $reflection->getMethod('inExceptArray');
        $exceptMethod->setAccessible(true);

        $routes = ['/protected', '/login', '/admin/settings', '/api/webhook'];

        foreach ($routes as $route) {
            $request = Request::create($route, 'POST');
            $this->assertFalse(
                $exceptMethod->invoke($middleware, $request),
                "Route {$route} should NOT be in the CSRF exclusion list"
            );
        }
    }

    protected function registerTestRoutes(): void
    {
        Route::post('/apple/callback', function () {
            return 'callback received';
        })->middleware('web');

        Route::post('/protected', function () {
            return 'protected received';
        })->middleware('web');
    }
}
