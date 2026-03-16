<?php

namespace GeneaLabs\LaravelSignInWithApple\Http\Controllers;

use GeneaLabs\LaravelSignInWithApple\Events\AppleAccessRevoked;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Handles Apple's server-to-server notifications.
 *
 * Apple sends notifications when users revoke access, transfer accounts,
 * or when email relay changes occur.
 *
 * Register this route in your app:
 *
 *     Route::post('/apple/notifications', [AppleNotificationController::class, 'handle']);
 */
class AppleNotificationController extends Controller
{
    /**
     * Handle an incoming Apple server-to-server notification.
     */
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

    /**
     * Decode the JWT payload from Apple (without signature verification for now).
     *
     * Apple signs notifications with their public keys. For production use,
     * you should verify the JWT signature against Apple's public keys.
     */
    protected function decodePayload(string $jwt): ?array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return null;
        }

        $claims = json_decode(base64_decode($parts[1]), true);

        return is_array($claims) ? $claims : null;
    }
}
