# Sign In With Apple for Laravel

## Installation

```sh
composer require genealabs/laravel-sign-in-with-apple
```

## Configuration

1. Create your app's client secret:

    ```sh
    TBA
    ```

1. Set the necessary environment variables in your `.env` file:

    ```env
    SIGN_IN_WITH_APPLE_LOGIN="/apple/login/controller/login/action"
    SIGN_IN_WITH_APPLE_REDIRECT="/apple/login/controller/callback/action"
    SIGN_IN_WITH_APPLE_CLIENT_ID="your app's service id as registered with Apple"
    SIGN_IN_WITH_APPLE_CLIENT_SECRET="your app's client secret as calculated"
    ```

## Implementation

### Login Button

Add the following blade directive to your login page:

```php
@signInWithAppleButton($color, $hasBorder, $type, $borderRadius)
```

| Parameter | Definition |
| --------- | ---------- |
| $color    | String, either "black" or "white. |
| $hasBorder | Boolean, either `true` or `false`. |
| $type      | String, either `"sign-in"` or `"continue"`. |
| $borderRadius | Integer, greater or equal to 0. |

### Controller
This implementation uses Socialite to get the login credentials. The following is an example implementation of the controller:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;

class AppleSigninController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function login()
    {
        return Socialite::driver("sign-in-with-apple")
            ->scopes(["name", "email"])
            ->redirect();
    }

    public function callback(Request $request)
    {
        $user = Socialite::driver("sign-in-with-apple")
            ->user();

        // do what you will with the resulting user object to complete the login process, for example save the user, and log the user in.
    }
}
```
