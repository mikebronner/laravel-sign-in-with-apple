<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use GeneaLabs\LaravelSignInWithApple\Exceptions\InvalidAppleCredentialsException;
use GeneaLabs\LaravelSignInWithApple\Providers\SignInWithAppleProvider;
use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use ReflectionMethod;

class TokenErrorHandlingTest extends UnitTestCase
{
    protected function makeProvider(): SignInWithAppleProvider
    {
        return new SignInWithAppleProvider(
            Request::create('/'),
            'test-client-id',
            'test-client-secret',
            'https://example.com/callback'
        );
    }

    public function testHandlesInvalidClientError(): void
    {
        $provider = $this->makeProvider();

        $response = new Response(400, [], json_encode(['error' => 'invalid_client']));
        $exception = new ClientException('Client error', new \GuzzleHttp\Psr7\Request('POST', '/'), $response);

        $method = new ReflectionMethod($provider, 'handleTokenError');
        $method->setAccessible(true);

        try {
            $method->invoke($provider, $exception);
            $this->fail('Expected InvalidAppleCredentialsException');
        } catch (InvalidAppleCredentialsException $e) {
            $this->assertStringContainsString('invalid_client', $e->getMessage());
            $this->assertStringContainsString('client_id', $e->getMessage());
            $this->assertStringContainsString('client_secret', $e->getMessage());
            $this->assertEquals('invalid_client', $e->getContext()['apple_error']);
            $this->assertSame($exception, $e->getPrevious());
        }
    }

    public function testHandlesInvalidGrantError(): void
    {
        $provider = $this->makeProvider();

        $response = new Response(400, [], json_encode(['error' => 'invalid_grant']));
        $exception = new ClientException('Client error', new \GuzzleHttp\Psr7\Request('POST', '/'), $response);

        $method = new ReflectionMethod($provider, 'handleTokenError');
        $method->setAccessible(true);

        try {
            $method->invoke($provider, $exception);
            $this->fail('Expected InvalidAppleCredentialsException');
        } catch (InvalidAppleCredentialsException $e) {
            $this->assertStringContainsString('invalid_grant', $e->getMessage());
            $this->assertStringContainsString('authorization code', $e->getMessage());
            $this->assertStringContainsString('redirect_uri', $e->getMessage());
            $this->assertEquals('invalid_grant', $e->getContext()['apple_error']);
        }
    }

    public function testHandlesUnknownAppleError(): void
    {
        $provider = $this->makeProvider();

        $response = new Response(400, [], json_encode(['error' => 'some_new_error']));
        $exception = new ClientException('Client error', new \GuzzleHttp\Psr7\Request('POST', '/'), $response);

        $method = new ReflectionMethod($provider, 'handleTokenError');
        $method->setAccessible(true);

        try {
            $method->invoke($provider, $exception);
            $this->fail('Expected InvalidAppleCredentialsException');
        } catch (InvalidAppleCredentialsException $e) {
            $this->assertStringContainsString('some_new_error', $e->getMessage());
            $this->assertEquals('some_new_error', $e->getContext()['apple_error']);
        }
    }
}

// Additional test appended for server error handling
