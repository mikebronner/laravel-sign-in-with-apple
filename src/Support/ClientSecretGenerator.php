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
    /**
     * Generate an Apple client_secret JWT.
     *
     * @param string $teamId    Your Apple Developer Team ID
     * @param string $clientId  Your Services ID (e.g. com.example.service)
     * @param string $keyId     The Key ID from your Apple private key
     * @param string $privateKey The contents of your .p8 private key file
     * @param int    $ttlDays   Token validity in days (max 180)
     *
     * @return string The signed JWT client secret
     *
     * @throws InvalidArgumentException If required parameters are empty or ttlDays exceeds 180
     */
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

    /**
     * Generate a client secret using values from the Laravel config/env.
     *
     * Expected env vars:
     *   APPLE_TEAM_ID, APPLE_CLIENT_ID (or SIGN_IN_WITH_APPLE_CLIENT_ID),
     *   APPLE_KEY_ID, APPLE_PRIVATE_KEY_PATH (or APPLE_PRIVATE_KEY)
     *
     * @param int $ttlDays Token validity in days (max 180)
     *
     * @return string The signed JWT client secret
     */
    public static function fromConfig(int $ttlDays = 180): string
    {
        $teamId = config('services.sign_in_with_apple.team_id', env('APPLE_TEAM_ID', ''));
        $clientId = config('services.sign_in_with_apple.client_id', env('APPLE_CLIENT_ID', ''));
        $keyId = config('services.sign_in_with_apple.key_id', env('APPLE_KEY_ID', ''));

        $privateKeyPath = env('APPLE_PRIVATE_KEY_PATH', '');
        $privateKey = env('APPLE_PRIVATE_KEY', '');

        if ($privateKeyPath && file_exists($privateKeyPath)) {
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

        if ($ttlDays < 1 || $ttlDays > 180) {
            throw new InvalidArgumentException('TTL must be between 1 and 180 days. Apple rejects longer-lived secrets.');
        }
    }
}
