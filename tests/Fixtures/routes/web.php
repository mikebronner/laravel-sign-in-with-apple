<?php

use GeneaLabs\LaravelSignInWithApple\Tests\Fixtures\Http\Controllers\SiwaController;

Route::view("/tests", "tests");
Route::get("/siwa-login", SiwaController::class . "@login");
Route::post("/siwa-callback", SiwaController::class . "@callback");
