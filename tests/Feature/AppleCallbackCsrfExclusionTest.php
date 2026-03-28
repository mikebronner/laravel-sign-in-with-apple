<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Feature;

use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use Illuminate\Support\Facades\Route;

class AppleCallbackCsrfExclusionTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Register routes in setUp so they're available for each test
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
     * Test that the callback route handles POST from Apple without CSRF token.
     * 
     * Simulates the exact flow: Apple POSTs to the callback without a CSRF token.
     * If CSRF is not excluded, this would return 419.
     */
    public function testAppleCallbackBypassesCsrfVerification(): void
    {
        // This is the critical test: POST without _token, no 419 error
        $response = $this->post('/apple/callback', [
            'code' => 'auth-code-abc123',
            'state' => 'state-xyz789',
        ]);

        // Should succeed (200), not trigger CSRF error (419)
        $this->assertNotEquals(419, $response->getStatusCode());
        $this->assertEquals('callback received', $response->getContent());
    }

    protected function registerTestRoutes(): void
    {
        Route::post('/apple/callback', function () {
            return 'callback received';
        })->middleware('web');

        Route::post('/protected', function () {
            return 'protected received';
        })->middleware('web');

        Route::post('/custom-apple-callback', function () {
            return 'custom callback received';
        })->middleware('web');
    }
}
