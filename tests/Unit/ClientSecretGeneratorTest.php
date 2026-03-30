<?php

namespace GeneaLabs\LaravelSignInWithApple\Tests\Unit;

use GeneaLabs\LaravelSignInWithApple\Support\ClientSecretGenerator;
use GeneaLabs\LaravelSignInWithApple\Tests\UnitTestCase;
use InvalidArgumentException;

class ClientSecretGeneratorTest extends UnitTestCase
{
    /**
     * Generate a test EC P-256 private key.
     */
    protected function generateTestKey(): string
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'ec' => ['curve_name' => 'prime256v1'],
        ]);

        openssl_pkey_export($key, $pem);

        return $pem;
    }

    public function testGeneratesValidJwt(): void
    {
        $privateKey = $this->generateTestKey();

        $jwt = ClientSecretGenerator::generate(
            teamId: 'TEAM123',
            clientId: 'com.example.service',
            keyId: 'KEY456',
            privateKey: $privateKey,
            ttlDays: 180,
        );

        $this->assertNotEmpty($jwt);

        // JWT should have 3 parts
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);

        // Decode header
        $header = json_decode(base64_decode($parts[0]), true);
        $this->assertEquals('ES256', $header['alg']);
        $this->assertEquals('KEY456', $header['kid']);

        // Decode payload
        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertEquals('TEAM123', $payload['iss']);
        $this->assertEquals('com.example.service', $payload['sub']);
        $this->assertEquals('https://appleid.apple.com', $payload['aud']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertEquals($payload['iat'] + (180 * 86400), $payload['exp']);
    }

    public function testCustomTtl(): void
    {
        $privateKey = $this->generateTestKey();

        $jwt = ClientSecretGenerator::generate(
            teamId: 'TEAM123',
            clientId: 'com.example.service',
            keyId: 'KEY456',
            privateKey: $privateKey,
            ttlDays: 30,
        );

        $parts = explode('.', $jwt);
        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertEquals($payload['iat'] + (30 * 86400), $payload['exp']);
    }

    public function testThrowsOnEmptyTeamId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('team_id');

        ClientSecretGenerator::generate(
            teamId: '',
            clientId: 'com.example.service',
            keyId: 'KEY456',
            privateKey: 'fake-key',
        );
    }

    public function testThrowsOnEmptyClientId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('client_id');

        ClientSecretGenerator::generate(
            teamId: 'TEAM123',
            clientId: '',
            keyId: 'KEY456',
            privateKey: 'fake-key',
        );
    }

    public function testThrowsOnEmptyKeyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('key_id');

        ClientSecretGenerator::generate(
            teamId: 'TEAM123',
            clientId: 'com.example.service',
            keyId: '',
            privateKey: 'fake-key',
        );
    }

    public function testThrowsOnEmptyPrivateKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('private key');

        ClientSecretGenerator::generate(
            teamId: 'TEAM123',
            clientId: 'com.example.service',
            keyId: 'KEY456',
            privateKey: '',
        );
    }

    public function testThrowsOnTtlExceeding180Days(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('180 days');

        ClientSecretGenerator::generate(
            teamId: 'TEAM123',
            clientId: 'com.example.service',
            keyId: 'KEY456',
            privateKey: 'fake-key',
            ttlDays: 181,
        );
    }

    public function testThrowsOnTtlLessThanOneDay(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ClientSecretGenerator::generate(
            teamId: 'TEAM123',
            clientId: 'com.example.service',
            keyId: 'KEY456',
            privateKey: 'fake-key',
            ttlDays: 0,
        );
    }

    public function testFromConfigReadsConfigValues(): void
    {
        $privateKey = $this->generateTestKey();

        config([
            'services.sign_in_with_apple.team_id' => 'CONFIG_TEAM',
            'services.sign_in_with_apple.client_id' => 'com.config.service',
            'services.sign_in_with_apple.key_id' => 'CONFIG_KEY',
            'services.sign_in_with_apple.private_key' => $privateKey,
            'services.sign_in_with_apple.private_key_path' => '',
        ]);

        $jwt = ClientSecretGenerator::fromConfig(ttlDays: 30);

        $parts = explode('.', $jwt);
        $payload = json_decode(base64_decode($parts[1]), true);

        $this->assertEquals('CONFIG_TEAM', $payload['iss']);
        $this->assertEquals('com.config.service', $payload['sub']);
    }

    public function testFromConfigPrefersKeyPathOverKeyContent(): void
    {
        $privateKey = $this->generateTestKey();
        $tempFile = tempnam(sys_get_temp_dir(), 'apple_key_');
        file_put_contents($tempFile, $privateKey);

        config([
            'services.sign_in_with_apple.team_id' => 'TEAM_PATH',
            'services.sign_in_with_apple.client_id' => 'com.path.service',
            'services.sign_in_with_apple.key_id' => 'KEY_PATH',
            'services.sign_in_with_apple.private_key' => 'this-would-fail-if-used',
            'services.sign_in_with_apple.private_key_path' => $tempFile,
        ]);

        $jwt = ClientSecretGenerator::fromConfig();

        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);

        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertEquals('TEAM_PATH', $payload['iss']);

        unlink($tempFile);
    }

    public function testFromConfigThrowsWhenKeyPathSetButFileMissing(): void
    {
        config([
            'services.sign_in_with_apple.team_id' => 'TEAM123',
            'services.sign_in_with_apple.client_id' => 'com.example.service',
            'services.sign_in_with_apple.key_id' => 'KEY456',
            'services.sign_in_with_apple.private_key' => 'fallback-key',
            'services.sign_in_with_apple.private_key_path' => '/nonexistent/path/key.p8',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');

        ClientSecretGenerator::fromConfig();
    }
}
