<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use GeneaLabs\LaravelSignInWithApple\Providers\SignInWithAppleProvider;
use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use Illuminate\Http\Request;

class MissingAuthCodeTest extends UnitTestCase
{
    public function testMissingCodeThrowsDescriptiveException(): void
    {
        // Request without 'code' parameter
        $request = Request::create('/callback', 'POST');

        $provider = new SignInWithAppleProvider(
            $request,
            'test-client-id',
            'test-client-secret',
            'https://example.com/callback'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('authorization code');
        $this->expectExceptionMessage('form_post');

        $provider->user();
    }

    public function testEmptyCodeThrowsDescriptiveException(): void
    {
        $request = Request::create('/callback', 'POST', ['code' => '']);

        $provider = new SignInWithAppleProvider(
            $request,
            'test-client-id',
            'test-client-secret',
            'https://example.com/callback'
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('POST requests');

        $provider->user();
    }

    public function testExceptionMentionsCsrf(): void
    {
        $request = Request::create('/callback', 'POST');

        $provider = new SignInWithAppleProvider(
            $request,
            'test-client-id',
            'test-client-secret',
            'https://example.com/callback'
        );

        try {
            $provider->user();
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('CSRF', $e->getMessage());
            $this->assertStringContainsString('form_post', $e->getMessage());
        }
    }
}
