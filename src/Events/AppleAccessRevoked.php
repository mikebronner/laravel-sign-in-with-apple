<?php

namespace GeneaLabs\LaravelSignInWithApple\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when Apple notifies that a user has revoked access to your app.
 *
 * Listen for this event to deactivate, log out, or clean up user data.
 *
 * Apple sends server-to-server notifications when users revoke access
 * via Settings > Apple ID > Sign-In & Security > Sign in with Apple.
 */
class AppleAccessRevoked
{
    use Dispatchable;

    public function __construct(
        public readonly string $sub,
        public readonly string $eventType,
        public readonly array $payload,
    ) {}
}
