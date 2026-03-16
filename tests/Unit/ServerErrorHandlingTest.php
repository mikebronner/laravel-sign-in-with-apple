<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use GeneaLabs\LaravelSignInWithApple\Providers\SignInWithAppleProvider;
use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use ReflectionMethod;

class ServerErrorHandlingTest extends UnitTestCase
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

    public function testHandles500ServerError(): void
    {
        $provider = $this->makeProvider();

        $response = new Response(500, [], 'Internal Server Error');
        $exception = new ServerException('Server error', new \GuzzleHttp\Psr7\Request('POST', '/'), $response);

        $method = new ReflectionMethod($provider, 'handleServerError');
        $method->setAccessible(true);

        try {
            $method->invoke($provider, $exception);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('temporarily unavailable', $e->getMessage());
            $this->assertStringContainsString('500', $e->getMessage());
            $this->assertSame($exception, $e->getPrevious());
        }
    }

    public function testHandles503ServiceUnavailable(): void
    {
        $provider = $this->makeProvider();

        $response = new Response(503, [], 'Service Unavailable');
        $exception = new ServerException('Server error', new \GuzzleHttp\Psr7\Request('POST', '/'), $response);

        $method = new ReflectionMethod($provider, 'handleServerError');
        $method->setAccessible(true);

        try {
            $method->invoke($provider, $exception);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('503', $e->getMessage());
            $this->assertStringContainsString('try again', $e->getMessage());
        }
    }
}
