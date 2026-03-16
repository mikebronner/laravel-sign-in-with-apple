<?php

namespace GeneaLabs\LaravelSignInWithApple\Providers;

use GeneaLabs\LaravelSignInWithApple\Exceptions\InvalidRedirectUrlException;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class SignInWithAppleProvider extends AbstractProvider implements ProviderInterface
{
    protected $encodingType = PHP_QUERY_RFC3986;
    protected $scopeSeparator = " ";

    protected function getAuthUrl($state)
    {
        $this->validateRedirectUrl();

        return $this->buildAuthUrlFromBase(
            'https://appleid.apple.com/auth/authorize',
            $state
        );
    }

    /**
     * Validate the redirect URL meets Apple's requirements.
     *
     * @throws InvalidRedirectUrlException
     */
    protected function validateRedirectUrl(): void
    {
        $url = $this->redirectUrl;

        if (empty($url)) {
            throw new InvalidRedirectUrlException(
                'Apple Sign In redirect URL is not configured. '
                . 'Set SIGN_IN_WITH_APPLE_REDIRECT in your .env file.',
                ['redirect_url' => $url],
            );
        }

        $parsed = parse_url($url);

        if ($parsed === false || ! isset($parsed['scheme']) || ! isset($parsed['host'])) {
            throw new InvalidRedirectUrlException(
                "Apple Sign In redirect URL is not a valid URL: {$url}. "
                . 'It must be a fully qualified URL (e.g. https://example.com/callback).',
                ['redirect_url' => $url],
            );
        }

        if ($parsed['scheme'] !== 'https' && $parsed['host'] !== 'localhost' && $parsed['host'] !== '127.0.0.1') {
            throw new InvalidRedirectUrlException(
                "Apple Sign In redirect URL must use HTTPS: {$url}. "
                . 'Apple requires HTTPS for all redirect URLs in production. '
                . 'HTTP is only allowed for localhost during development.',
                ['redirect_url' => $url, 'scheme' => $parsed['scheme']],
            );
        }
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

    public function getAccessToken($code)
    {
        $response = $this->getHttpClient()
            ->post(
                $this->getTokenUrl(),
                [
                    'headers' => [
                        'Authorization' => 'Basic '. base64_encode(
                            $this->clientId . ':' . $this->clientSecret
                        ),
                    ],
                    'body' => $this->getTokenFields($code),
                ]
            );

        return $this->parseAccessToken($response->getBody());
    }

    protected function parseAccessToken($response)
    {
        $data = $response->json();

        return $data['access_token'];
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
}
