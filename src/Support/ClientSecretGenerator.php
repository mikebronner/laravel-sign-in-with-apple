<?php

namespace GeneaLabs\LaravelSignInWithApple\Support;

use Firebase\JWT\JWT;
use InvalidArgumentException;

/**
 * Generate Apple Sign In client secret JWTs in PHP.
 *
 * Apple requires a signed JWT as the client_secret when exchanging authorization
 * codes for tokens. This utility generates that JWT using your private key,
 * eliminating the need for the Ruby script in the README.
 *
 * Usage:
 *     $secret = ClientSecretGenerator::generate(
 *         teamId: 'YOUR_TEAM_ID',
 *         clientId: 'com.example.service',
 *         keyId: 'YOUR_KEY_ID',
 *         privateKey: file_get_contents('/path/to/key.p8'),
 *         ttlDays: 180,
 *     );
 */
class ClientSecretGenerator
{
    public static function generate(
        string $teamId,
        string $clientId,
        string $keyId,
        string $privateKey,
        int $ttlDays = 180,
    ): string {
        static::validate($teamId, $clientId, $keyId, $privateKey, $ttlDays);

        $now = time();

        $payload = [
            'iss' => $teamId,
            'iat' => $now,
            'exp' => $now + ($ttlDays * 86400),
            'aud' => 'https://appleid.apple.com',
            'sub' => $clientId,
        ];

        return JWT::encode($payload, $privateKey, 'ES256', $keyId);
    }

    public static function fromConfig(int $ttlDays = 180): string
    {
        $teamId = config('services.apple.sign_in.team_id', '');
        $clientId = config('services.apple.sign_in.client_id', '');
        $keyId = config('services.apple.sign_in.key_id', '');

        $privateKeyPath = config('services.apple.sign_in.private_key_path', '');
        $privateKey = config('services.apple.sign_in.private_key', '');

        if ($privateKeyPath) {
            if (! file_exists($privateKeyPath)) {
                throw new InvalidArgumentException(
                    "Apple private key file not found at: {$privateKeyPath}. "
                    . 'Check your SIGN_IN_WITH_APPLE_PRIVATE_KEY_PATH env var.'
                );
            }

            $privateKey = file_get_contents($privateKeyPath);
        }

        return static::generate($teamId, $clientId, $keyId, $privateKey, $ttlDays);
    }

    protected static function validate(
        string $teamId,
        string $clientId,
        string $keyId,
        string $privateKey,
        int $ttlDays,
    ): void {
        if (empty($teamId)) {
            throw new InvalidArgumentException('Apple team_id is required.');
        }

        if (empty($clientId)) {
            throw new InvalidArgumentException('Apple client_id (Services ID) is required.');
        }

        if (empty($keyId)) {
            throw new InvalidArgumentException('Apple key_id is required.');
        }

        if (empty($privateKey)) {
            throw new InvalidArgumentException('Apple private key is required. Provide the .p8 file contents.');
        }

        if (
            $ttlDays < 1
            || $ttlDays > 180
        ) {
            throw new InvalidArgumentException('TTL must be between 1 and 180 days. Apple rejects longer-lived secrets.');
        }
    }
}
