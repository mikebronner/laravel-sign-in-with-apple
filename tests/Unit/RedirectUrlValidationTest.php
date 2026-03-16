<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use GeneaLabs\LaravelSignInWithApple\Exceptions\InvalidRedirectUrlException;
use GeneaLabs\LaravelSignInWithApple\Providers\SignInWithAppleProvider;
use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use Illuminate\Http\Request;
use ReflectionMethod;

class RedirectUrlValidationTest extends UnitTestCase
{
    protected function makeProvider(string $redirectUrl): SignInWithAppleProvider
    {
        return new SignInWithAppleProvider(
            Request::create('/'),
            'test-client-id',
            'test-client-secret',
            $redirectUrl
        );
    }

    public function testValidHttpsUrlPasses(): void
    {
        $provider = $this->makeProvider('https://example.com/callback');

        $method = new ReflectionMethod($provider, 'validateRedirectUrl');
        $method->setAccessible(true);
        $method->invoke($provider);

        $this->assertTrue(true);
    }

    public function testLocalhostHttpUrlPasses(): void
    {
        $provider = $this->makeProvider('http://localhost/callback');

        $method = new ReflectionMethod($provider, 'validateRedirectUrl');
        $method->setAccessible(true);
        $method->invoke($provider);

        $this->assertTrue(true);
    }

    public function testLoopbackHttpUrlPasses(): void
    {
        $provider = $this->makeProvider('http://127.0.0.1/callback');

        $method = new ReflectionMethod($provider, 'validateRedirectUrl');
        $method->setAccessible(true);
        $method->invoke($provider);

        $this->assertTrue(true);
    }

    public function testEmptyUrlThrows(): void
    {
        $provider = $this->makeProvider('');

        $method = new ReflectionMethod($provider, 'validateRedirectUrl');
        $method->setAccessible(true);

        $this->expectException(InvalidRedirectUrlException::class);
        $this->expectExceptionMessage('not configured');

        $method->invoke($provider);
    }

    public function testHttpUrlThrows(): void
    {
        $provider = $this->makeProvider('http://example.com/callback');

        $method = new ReflectionMethod($provider, 'validateRedirectUrl');
        $method->setAccessible(true);

        $this->expectException(InvalidRedirectUrlException::class);
        $this->expectExceptionMessage('HTTPS');

        $method->invoke($provider);
    }

    public function testInvalidUrlThrows(): void
    {
        $provider = $this->makeProvider('not-a-url');

        $method = new ReflectionMethod($provider, 'validateRedirectUrl');
        $method->setAccessible(true);

        $this->expectException(InvalidRedirectUrlException::class);
        $this->expectExceptionMessage('not a valid URL');

        $method->invoke($provider);
    }

    public function testExceptionIncludesContext(): void
    {
        $provider = $this->makeProvider('http://example.com/callback');

        $method = new ReflectionMethod($provider, 'validateRedirectUrl');
        $method->setAccessible(true);

        try {
            $method->invoke($provider);
            $this->fail('Expected InvalidRedirectUrlException');
        } catch (InvalidRedirectUrlException $e) {
            $this->assertEquals('http://example.com/callback', $e->getContext()['redirect_url']);
            $this->assertEquals('http', $e->getContext()['scheme']);
        }
    }

    public function testValidUrlDoesNotBlockRedirect(): void
    {
        $provider = $this->makeProvider('https://example.com/callback');
        $provider->stateless();

        $response = $provider->redirect();

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('appleid.apple.com', $response->getTargetUrl());
    }
}
