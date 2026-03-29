<?php

namespace GeneaLabs\LaravelSignInWithApple\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Laravel\Socialite\Contracts\User;

class AppleSignInCallback
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {
    }
}
