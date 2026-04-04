<?php

namespace App\Extensions;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class SynologyProvider extends AbstractProvider implements ProviderInterface
{
    // OIDC strictly requires the 'openid' scope
    protected $scopes = ['groups', 'openid'];

    protected function getAuthUrl($state)
    {
        // Synology usually uses SSOOauth.cgi for OIDC authorization
        return $this->buildAuthUrlFromBase(config('services.synology.host') . '/SSOOauth.cgi', $state);
    }

    protected function getTokenUrl()
    {
        // Synology usually uses SSOAccessToken.cgi for token exchange
        return config('services.synology.host') . '/SSOAccessToken.cgi';
    }

    protected function getUserByToken($token)
    {
        // Synology usually uses SSOUserInfo.cgi to get user details
        $response = $this->getHttpClient()->get(config('services.synology.host') . '/SSOUserInfo.cgi', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    protected function mapUserToObject(array $user)
    {
        // Map the fields returned by Synology to standard Socialite user object
        return (new User)->setRaw($user)->map([
            'id' => $user['sub'] ?? $user['id'] ?? null,
            'nickname' => $user['username'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
        ]);
    }
}