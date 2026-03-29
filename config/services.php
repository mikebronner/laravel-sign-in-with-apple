<?php

return [
    "sign_in_with_apple" => [
        "client_id" => env("SIGN_IN_WITH_APPLE_CLIENT_ID"),
        "client_secret" => env("SIGN_IN_WITH_APPLE_CLIENT_SECRET"),
        "redirect" => env("SIGN_IN_WITH_APPLE_REDIRECT"),
        // Auto-register package routes
        "routes" => [
            "enabled" => true,
            "redirect_route" => "apple/redirect",
            "callback_route" => "apple/callback",
            "callback_redirect" => "/",
        ],
    ],
];
