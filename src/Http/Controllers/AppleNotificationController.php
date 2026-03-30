<?php

declare(strict_types=1);

namespace GeneaLabs\LaravelSignInWithApple\Http\Controllers;

use DomainException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GeneaLabs\LaravelSignInWithApple\Events\AppleAccessRevoked;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use UnexpectedValueException;

class AppleNotificationController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->input('payload');

        if (empty($payload)) {
            return response()->json(['error' => 'Missing payload'], 400);
        }

        $claims = $this->decodePayload($payload);

        if ($claims === null) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $eventType = $claims['events']['type'] ?? null;
        $sub = $claims['events']['sub'] ?? null;

        if (empty($sub)) {
            return response()->json(['error' => 'Missing subject'], 400);
        }

        if (in_array($eventType, ['consent-revoked', 'account-delete'])) {
            AppleAccessRevoked::dispatch($sub, $eventType, $claims);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function decodePayload(string $jwt): ?array
    {
        try {
            $keys = $this->getApplePublicKeys();
            $decoded = JWT::decode($jwt, JWK::parseKeySet($keys));

            return (array) json_decode(json_encode($decoded), true);
        } catch (UnexpectedValueException | DomainException | InvalidArgumentException) {
            return null;
        }
    }

    protected function getApplePublicKeys(): array
    {
        return Cache::remember('apple-auth-keys', 3600, function () {
            $response = Http::get('https://appleid.apple.com/auth/keys');

            if (! $response->successful()) {
                throw new UnexpectedValueException('Unable to fetch Apple public keys.');
            }

            return $response->json();
        });
    }
}
