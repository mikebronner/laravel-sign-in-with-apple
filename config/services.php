<?php

return [
    "apple" => [
        "client_id" => env("APPLE_CLIENT_ID", env("SIGN_IN_WITH_APPLE_CLIENT_ID")),
        "client_secret" => env("APPLE_CLIENT_SECRET", env("SIGN_IN_WITH_APPLE_CLIENT_SECRET")),
        "redirect" => env("APPLE_REDIRECT", env("SIGN_IN_WITH_APPLE_REDIRECT")),
        // Auto-register package routes
        "routes" => [
            "enabled" => true,
            "redirect_route" => "apple/redirect",
            "callback_route" => "apple/callback",
            "callback_redirect" => "/",
        ],
    ],
];
