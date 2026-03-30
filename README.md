# Sign In With Apple for Laravel

![repository-open-graph-template](https://user-images.githubusercontent.com/1791050/66706715-21cc0800-eceb-11e9-90b4-0a6ae3dd97b7.png)

## Supporting This Package

This is an MIT-licensed open source project with its ongoing development made possible by the support of the community. If you'd like to support this, and our other packages, please consider sponsoring us via the button above.

We thank the following sponsors for their generosity, please take a moment to check them out:

- [LIX](https://lix-it.com)

## Table of Contents
- [Requirements](#Requirements)
- [Installation](#Installation)
- [Configuration](#Configuration)
- [Implementation](#Implementation)
  - [Button](#Button)
  - [Controller](#Controller)
- [Testing](#Testing)

<a name="Requirements"></a>
## Requirements

- PHP 8.2+
- Laravel 10.0+
- Socialite 5.0+
- Apple Developer Subscription

### Version Support

| Laravel | PHP       | Package |
|---------|-----------|----------|
| 10.x    | 8.2+      | 5.x     |
| 11.x    | 8.2+      | 5.x     |
| 12.x    | 8.2+      | 5.x     |
| 13.x    | 8.3+      | 5.x     |

<a name="Installation"></a>
## Installation

<a href="https://vimeo.com/366353988">![siwa-video-cover](https://user-images.githubusercontent.com/1791050/66785970-af4a5c00-ee93-11e9-9470-b42af6f237c9.png)</a>

1. Install the composer package:
    ```sh
    composer require mikebronner/laravel-sign-in-with-apple
    ```

    We also recommend using [geneaLabs/laravel-socialiter](https://github.com/GeneaLabs/laravel-socialiter)
    to automatically manage user resolution and persistence:

    ```sh
    composer require genealabs/laravel-socialiter
    ```

<a name="Configuration"></a>
## Configuration

1. Create an `App ID` for your website (https://developer.apple.com/account/resources/identifiers/list/bundleId) with the following details:
    - Platform: iOS, tvOS, watchOS (I'm unsure if either choice has an effect for web apps)
    - Description: (something like "example.com app id")
    - Bundle ID (Explicit): com.example.id (or something similar)
    - Check "Sign In With Apple"
2. Create a `Service ID` for your website (https://developer.apple.com/account/resources/identifiers/list/serviceId) with the following details:
    - Description: (something like "example.com service id")
    - Identifier: com.example.service (or something similar)
    - Check "Sign In With Apple"
    - Configure "Sign In With Apple":
        - Primary App Id: (select the primary app id created in step 1)
        - Web Domain: example.com (the domain of your web site)
        - Return URLs: https://example.com/apple-signin (the route pointing to the callback method in your controller)
        - Click "Save".
        - Click the "Edit" button to edit the details of the "Sign In With Apple"
            configuration we just created.
        - If you haven't verified the domain yet, download the verification file,
            upload it to https://example.com/.well-known/apple-developer-domain-association.txt, and then click the "Verify"
            button.
3. Create a `Private Key` for your website (https://developer.apple.com/account/resources/authkeys/list) with the following details:
    - Key Name:
    - Check "Sign In With Apple"
    - Configure "Sign In With Apple":
        - Primary App ID: (select the primary app id created in step 1)
        - Click "Save"
    - Click "Continue"
    - Click "Register"
    - Click "Download"
    - Rename the downloaded file to `key.txt`
4. Create your app's client secret:
    - Install the JWT Gem:
        ```sh
        sudo gem install jwt
        ```

    - Create a file called `client_secret.rb` to process the private key:
        ```ruby
        require 'jwt'

        key_file = 'key.txt'
        team_id = ''
        client_id = ''
        key_id = ''

        ecdsa_key = OpenSSL::PKey::EC.new IO.read key_file

        headers = {
        'kid' => key_id
        }

        claims = {
            'iss' => team_id,
            'iat' => Time.now.to_i,
            'exp' => Time.now.to_i + 86400*180,
            'aud' => 'https://appleid.apple.com',
            'sub' => client_id,
        }

        token = JWT.encode claims, ecdsa_key, 'ES256', headers

        puts token
        ```

    - Fill in the following fields:
        - `team_id`: This can be found on the top-right corner when logged into
            your Apple Developer account, right under your name.
        - `client_id`: This is the identifier from the Service Id created in step
            2 above, for example com.example.service
        - `key_id`: This is the identifier of the private key created in step 3
            above.
    - Save the file and run it from the terminal. It will spit out a JWT which is
        your client secret, which you will need to add to your `.env` file in the
        next step.
        ```sh
        ruby client_secret.rb
        ```


#### Alternative: Generate client_secret in PHP

Instead of using the Ruby script above, you can generate the client secret JWT directly in PHP using this package's built-in helper:

```php
use GeneaLabs\LaravelSignInWithApple\Support\ClientSecretGenerator;

// One-off generation
$secret = ClientSecretGenerator::generate(
    teamId: 'YOUR_TEAM_ID',
    clientId: 'com.example.service',
    keyId: 'YOUR_KEY_ID',
    privateKey: file_get_contents(storage_path('keys/apple-auth-key.p8')),
    ttlDays: 180, // Max 180 days
);

// Or use config/env values automatically
$secret = ClientSecretGenerator::fromConfig();
```

You can use an Artisan command or scheduled task to auto-rotate the secret before it expires:

```php
// In a scheduled command or service provider
$secret = ClientSecretGenerator::fromConfig(ttlDays: 180);
config(['services.sign_in_with_apple.client_secret' => $secret]);
```

Required env vars for `fromConfig()`:
```env
SIGN_IN_WITH_APPLE_TEAM_ID=your-team-id
SIGN_IN_WITH_APPLE_KEY_ID=your-key-id
SIGN_IN_WITH_APPLE_PRIVATE_KEY_PATH=/path/to/key.p8
```

5. Set the necessary environment variables in your `.env` file:

    ```env
    SIGN_IN_WITH_APPLE_REDIRECT="/apple/login/controller/callback/action"
    SIGN_IN_WITH_APPLE_CLIENT_ID="your app's service id as registered with Apple"
    SIGN_IN_WITH_APPLE_CLIENT_SECRET="your app's client secret as calculated in step 4"
    ```

    > **Note:** The `SIGN_IN_WITH_APPLE_LOGIN` environment variable has been removed.
    > Login routes should be defined in your application's route files instead.
    > See the [Migration Guide](#MigrationGuide) below if upgrading from an older version.

### Redirect URL Requirements

Apple has strict requirements for the redirect (callback) URL:

- **Must use HTTPS** — HTTP is rejected in production. Only `http://localhost` is allowed for local development.
- **Must exactly match** the Return URL registered in your Apple Developer account under Services ID configuration.
- **No query parameters** — Apple will reject URLs with query strings.
- **No fragments** — Hash fragments are not supported.

The package validates your redirect URL at auth initiation and throws an `InvalidRedirectUrlException` with a clear error message if it doesn't meet these requirements.

Common mistakes:
- Using `http://` instead of `https://` in production
- Having a trailing slash mismatch between config and Apple Developer Console
- Forgetting to add the URL to your Services ID in the Apple Developer portal

<a name="Implementation"></a>
## Implementation

<a name="LoginButton"></a>
### Button

Add the following blade directive to your login page:

```php
@signInWithApple($color, $hasBorder, $type, $borderRadius)
```

| Parameter | Definition |
| --------- | ---------- |
| $color    | String, either "black" or "white. |
| $hasBorder | Boolean, either `true` or `false`. |
| $type      | String, either `"sign-in"` or `"continue"`. |
| $borderRadius | Integer, greater or equal to 0. |

<a name="CsrfExclusion"></a>
### CSRF Exclusion

Apple sends the authorization response as a **POST** request to your callback URL. This would normally trigger a `419 | Page Expired` (CSRF token mismatch) error. **This package automatically excludes the configured callback route from CSRF verification**, so no additional configuration is required.

This is safe because Apple callbacks are validated via the OAuth `state` parameter, not CSRF tokens. If you need to manually exclude the route for any reason, you can use one of these approaches:

**Option A: Exclude the route in your VerifyCsrfToken middleware** (Laravel 10 and earlier):

```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    '/apple/callback', // or whatever your callback URL is
];
```

**Option B: Use `withoutMiddleware` on the route** (Laravel 11+):

```php
Route::post('/apple/callback', [AppleSigninController::class, 'callback'])
    ->withoutMiddleware([\\Illuminate\\Foundation\\Http\\Middleware\\VerifyCsrfToken::class]);
```

<a name="Controller"></a>
### Controller

This implementation uses Socialite to get the login credentials. The following is an example implementation of the controller:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use GeneaLabs\LaravelSocialiter\Facades\Socialiter;
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
        // get abstract user object, not persisted
        $user = Socialite::driver("sign-in-with-apple")
            ->user();

        // or use Socialiter to automatically manage user resolution and persistence
        $user = Socialiter::driver("sign-in-with-apple")
            ->login();
    }
}
```

Note that when processing the returned `$user` object, it is critical to know that the `sub` element is the unique identifier for the user, **NOT** the email address. For more details, visit https://developer.apple.com/documentation/signinwithapplerestapi/authenticating_users_with_sign_in_with_apple.



### Missing Authorization Code

If you receive an error about a missing authorization code in the callback, check:

1. **Your callback route must accept POST requests** — Apple uses `response_mode=form_post`, which means the authorization code is sent as a POST form parameter, not a URL query parameter. Use `Route::post()`, not `Route::get()`.

2. **CSRF protection must be disabled for the callback** — Since Apple's POST doesn't include a CSRF token, Laravel will return a 419 error and the code will never reach your controller. See the [CSRF Exclusion](#CsrfExclusion) section above.

3. **The redirect URL must exactly match** — The URL in your `.env` (`SIGN_IN_WITH_APPLE_REDIRECT`) must exactly match the Return URL configured in your Apple Developer account, including the protocol, domain, and path.

### Handling Revoked Access

When a user revokes your app's access via Apple ID settings, Apple sends a server-to-server notification. This package provides an `AppleNotificationController` and `AppleAccessRevoked` event to handle this.

**1. Register the notification route:**

```php
use GeneaLabs\LaravelSignInWithApple\Http\Controllers\AppleNotificationController;

Route::post('/apple/notifications', [AppleNotificationController::class, 'handle'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
```

**2. Listen for the revocation event:**

```php
// In EventServiceProvider or a listener
use GeneaLabs\LaravelSignInWithApple\Events\AppleAccessRevoked;

Event::listen(AppleAccessRevoked::class, function (AppleAccessRevoked $event) {
    // $event->sub — the Apple user ID
    // $event->eventType — 'consent-revoked' or 'account-delete'
    
    $user = User::where('apple_id', $event->sub)->first();
    if ($user) {
        // Deactivate, log out, or clean up
    }
});
```

**3. Configure the endpoint in Apple Developer:**

Add your notification URL (`https://example.com/apple/notifications`) in the Apple Developer portal under your Services ID configuration.

**Important:** Apple only provides the user's name and email on the **first** authorization. If a user revokes access and re-authenticates, Apple treats it as a new sign-in but may not provide the name again. Always store the user's name on first sign-in.

**Handling re-authentication after revocation:**

When a user revokes and re-authenticates, Apple may assign a new `sub` value. The Socialite user object includes an `is_returning_user` flag to help you detect this:

```php
$appleUser = Socialite::driver('sign-in-with-apple')->user();

if ($appleUser['is_returning_user']) {
    // User re-authenticated after revocation — match by email
    $user = User::where('email', $appleUser->getEmail())->first();
} else {
    // First-time sign-in — name is available
    $user = User::firstOrCreate(
        ['apple_id' => $appleUser->getId()],
        ['name' => $appleUser->getName(), 'email' => $appleUser->getEmail()]
    );
}
```

**Security:** The notification endpoint verifies Apple's JWT signatures against Apple's public keys (fetched from `https://appleid.apple.com/auth/keys`). Keys are cached for 1 hour. Unsigned or forged notifications are rejected.

<a name="Testing"></a>
## Testing

This package includes unit, feature, and browser tests. Unit and feature tests run without any additional dependencies:

```sh
vendor/bin/phpunit --testsuite=Unit,Feature
```

### Browser Tests

Browser tests use [Laravel Dusk](https://laravel.com/docs/dusk) via `orchestra/testbench-dusk` and require a Chrome-based browser installed on your machine.

**Install Chrome or Chromium:**

- **Google Chrome** (recommended):
  ```sh
  # macOS
  brew install --cask google-chrome

  # Ubuntu/Debian
  sudo apt-get install google-chrome-stable
  ```

- **Chromium** (lighter alternative):
  ```sh
  # macOS
  brew install --cask chromium
  ```

  On macOS, Chromium may be blocked by Gatekeeper. Remove the quarantine flag after installing:
  ```sh
  xattr -dr com.apple.quarantine /Applications/Chromium.app
  ```

**Install Chromedriver:**

The package uses `orchestra/dusk-updater` (included as a dev dependency) to manage the Chromedriver binary. Run this to auto-detect your Chrome version and install the matching Chromedriver:

```sh
# If Google Chrome is installed (auto-detected):
vendor/bin/dusk-updater detect --auto-update --no-interaction

# If using Chromium on macOS (must specify the binary path):
vendor/bin/dusk-updater detect --chrome-dir="/Applications/Chromium.app/Contents/MacOS/Chromium" --auto-update --no-interaction
```

**Run browser tests:**

```sh
vendor/bin/phpunit --testsuite=Browser
```

**Run all tests:**

```sh
vendor/bin/phpunit
```

<a name="MigrationGuide"></a>
## Migration Guide

### Upgrading from versions prior to the config update

The configuration has been simplified. The following changes were made:

| Old Key | New Behavior |
|---------|-------------|
| `SIGN_IN_WITH_APPLE_LOGIN` | **Removed.** Define your login route in your application's route files instead of the config. |
| `SIGN_IN_WITH_APPLE_REDIRECT` | Unchanged — still used for the OAuth callback URL. |
| `SIGN_IN_WITH_APPLE_CLIENT_ID` | Unchanged. |
| `SIGN_IN_WITH_APPLE_CLIENT_SECRET` | Unchanged. |

**Steps to upgrade:**

1. Remove `SIGN_IN_WITH_APPLE_LOGIN` from your `.env` file.
2. If you relied on the `login` config key for the `@signInWithApple` Blade directive button URL, define the route in your application's routes file and update the directive or link accordingly.
3. The package will emit a `E_USER_DEPRECATED` notice if the old `login` key is still present, giving you time to migrate before it is fully removed.

----------

#### Credits
1. https://developer.okta.com/blog/2019/06/04/what-the-heck-is-sign-in-with-apple
2. https://developer.apple.com/sign-in-with-apple/get-started

----------

## Commitment to Quality
During package development I try as best as possible to embrace good design and development practices, to help ensure that this package is as good as it can
be. My checklist for package development includes:

-   ✅ Achieve as close to 100% code coverage as possible using unit tests.
-   ✅ Eliminate any issues identified by SensioLabs Insight and Scrutinizer.
-   ✅ Be fully PSR1, PSR2, and PSR4 compliant.
-   ✅ Include comprehensive documentation in README.md.
-   ✅ Provide an up-to-date CHANGELOG.md which adheres to the format outlined
    at <http://keepachangelog.com>.
-   ✅ Have no PHPMD or PHPCS warnings throughout all code.

## Troubleshooting

### `invalid_client` Error
This means Apple rejected your credentials. Common causes:
- **Wrong Client ID**: `SIGN_IN_WITH_APPLE_CLIENT_ID` must be your **Services ID** (not your App ID or Team ID).
- **Wrong Client Secret**: The JWT must be signed with the correct private key, team ID, and key ID.
- **Key revoked**: Check that your key is still active in your Apple Developer account under Keys.

### `invalid_grant` Error
This means the authorization code was rejected. Common causes:
- **Code already used**: Apple authorization codes are single-use. If you refresh the callback page, the code will be invalid.
- **Code expired**: Codes are valid for a short time (approximately 5 minutes).
- **Redirect URI mismatch**: The `SIGN_IN_WITH_APPLE_REDIRECT` must exactly match the URL registered in your Apple Developer account.
- **Client secret expired**: Apple client secret JWTs are valid for up to 6 months. Regenerate if expired.

### Missing Configuration
If you see `Sign In With Apple is missing required config`, ensure you have set the following in your `.env`:
```
SIGN_IN_WITH_APPLE_CLIENT_ID=your-services-id
SIGN_IN_WITH_APPLE_CLIENT_SECRET=your-generated-jwt
SIGN_IN_WITH_APPLE_REDIRECT=https://your-app.com/callback
SIGN_IN_WITH_APPLE_LOGIN=/login/apple
```

## Contributing
Please observe and respect all aspects of the included [Code of Conduct](https://github.com/GeneaLabs/laravel-sign-in-with-apple/blob/master/CODE_OF_CONDUCT.md).

### Reporting Issues
When reporting issues, please fill out the included template as completely as
possible. Incomplete issues may be ignored or closed if there is not enough
information included to be actionable.

### Submitting Pull Requests
Please review the [Contribution Guidelines](https://github.com/GeneaLabs/laravel-sign-in-with-apple/blob/master/CONTRIBUTING.md). Only PRs that meet all criterium will be accepted.

## If you ❤️ open-source software, give the repos you use a ⭐️.
We have included the awesome `symfony/thanks` composer package as a dev dependency. Let your OS package maintainers know you appreciate them by starring the packages you use. Simply run `composer thanks` after installing this package. (And not to worry, since it's a dev-dependency it won't be installed in your live environment.)
