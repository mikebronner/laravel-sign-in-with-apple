<?php

namespace GeneaLabs\LaravelSignInWithApple\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Bypass CSRF verification for the Apple Sign In callback route.
 *
 * Apple sends the authorization response as a form POST to the callback URL,
 * which does not include a CSRF token. Apply this middleware to the Apple
 * callback route to prevent 419 errors.
 */
class DisableCsrfForAppleCallback
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
