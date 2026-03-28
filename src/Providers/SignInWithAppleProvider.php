<?php

namespace GeneaLabs\LaravelSignInWithApple\Providers;

use GeneaLabs\LaravelSignInWithApple\Exceptions\InvalidAppleCredentialsException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class SignInWithAppleProvider extends AbstractProvider implements ProviderInterface
{
    protected $encodingType = PHP_QUERY_RFC3986;
    protected $scopeSeparator = " ";

    /**
     * Error descriptions for Apple token endpoint errors.
     */
    protected static array $errorDescriptions = [
        'invalid_client' => 'The client_id or client_secret is incorrect. '
            . 'Verify your APPLE_CLIENT_ID matches your Apple Services ID '
            . 'and your APPLE_CLIENT_SECRET (JWT) is correctly generated with the right team_id and key_id.',
        'invalid_grant' => 'The authorization code is invalid, expired, or has already been used. '
            . 'This can also occur if the redirect_uri does not match the one registered with Apple, '
            . 'or if the client_secret JWT has expired (they are valid for up to 6 months).',
    ];

    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase(
            'https://appleid.apple.com/auth/authorize',
            $state
        );
    }

    protected function getCodeFields($state = null)
    {
        $fields = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUrl,
            'scope' => $this->formatScopes($this->getScopes(), $this->scopeSeparator),
            'response_type' => 'code',
            'response_mode' => 'form_post',
        ];

        if ($this->usesState()) {
            $fields['state'] = $state;
        }

        return array_merge($fields, $this->parameters);
    }

    protected function getTokenUrl()
    {
        return "https://appleid.apple.com/auth/token";
    }

    /**
     * {@inheritdoc}
     *
     * Wraps Apple token endpoint errors with descriptive messages.
     */
    public function getAccessTokenResponse($code)
    {
        try {
            return parent::getAccessTokenResponse($code);
        } catch (ClientException $e) {
            $this->handleTokenError($e);
        } catch (ServerException $e) {
            $this->handleServerError($e);
        }
    }

    protected function getTokenFields($code)
    {
        $fields = parent::getTokenFields($code);
        $fields["grant_type"] = "authorization_code";

        return $fields;
    }

    protected function getUserByToken($token)
    {
        $claims = explode('.', $token)[1];

        return json_decode(base64_decode($claims), true);
    }

    public function user()
    {
        $response = $this->getAccessTokenResponse($this->getCode());

        $user = $this->mapUserToObject($this->getUserByToken(
            Arr::get($response, 'id_token')
        ));

        return $user
            ->setToken(Arr::get($response, 'id_token'))
            ->setRefreshToken(Arr::get($response, 'refresh_token'))
            ->setExpiresIn(Arr::get($response, 'expires_in'));
    }

    protected function mapUserToObject(array $user)
    {
        if (request()->filled("user")) {
            $userRequest = json_decode(request("user"), true);

            if (array_key_exists("name", $userRequest)) {
                $user["name"] = $userRequest["name"];
                $fullName = trim(
                    ($user["name"]['firstName'] ?? "")
                    . " "
                    . ($user["name"]['lastName'] ?? "")
                );
            }
        }

        return (new User)
            ->setRaw($user)
            ->map([
                "id" => $user["sub"],
                "name" => $fullName ?? null,
                "email" => $user["email"] ?? null,
            ]);
    }

    /**
     * Handle a ServerException (5xx) from the Apple token endpoint.
     *
     * Apple's servers occasionally return 500 errors. This wraps them
     * in a descriptive exception so apps can handle them gracefully.
     *
     * @throws \RuntimeException
     */
    protected function handleServerError(ServerException $exception): never
    {
        $statusCode = $exception->getResponse()->getStatusCode();

        throw new \RuntimeException(
            "Apple Sign In is temporarily unavailable (HTTP {$statusCode}). "
            . 'Apple\'s authentication servers returned a server error. '
            . 'This is typically a temporary issue on Apple\'s side. '
            . 'Please try again in a few minutes.',
            $statusCode,
            $exception,
        );
    }

    /**
     * Handle a ClientException from the Apple token endpoint.
     *
     * Parses the JSON error response and throws a descriptive exception.
     *
     * @throws InvalidAppleCredentialsException
     */
    protected function handleTokenError(ClientException $exception): never
    {
        $body = (string) $exception->getResponse()->getBody();
        $data = json_decode($body, true) ?? [];
        $error = $data['error'] ?? 'unknown_error';

        $description = static::$errorDescriptions[$error]
            ?? "Apple returned error: {$error}. Check your Sign In With Apple configuration.";

        throw new InvalidAppleCredentialsException(
            "Sign In With Apple token error [{$error}]: {$description}",
            [
                'apple_error' => $error,
                'client_id' => $this->clientId,
                'redirect_url' => $this->redirectUrl,
            ],
            $exception->getCode(),
            $exception,
        );
    }
}
