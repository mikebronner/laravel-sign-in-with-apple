<?php

use GeneaLabs\LaravelSignInWithApple\Http\Controllers\AppleSignInController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => ['web'],
], function () {
    $redirectPath = config('services.apple.routes.redirect_route', 'apple/redirect');
    $callbackPath = config('services.apple.routes.callback_route', 'apple/callback');

    Route::get($redirectPath, [AppleSignInController::class, 'redirect'])
        ->name('apple.redirect');

    Route::post($callbackPath, [AppleSignInController::class, 'callback'])
        ->withoutMiddleware([VerifyCsrfToken::class])
        ->name('apple.callback');
});
