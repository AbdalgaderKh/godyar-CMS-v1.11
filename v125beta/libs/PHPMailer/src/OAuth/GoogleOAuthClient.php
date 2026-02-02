<?php

namespace GoogleOAuthPHPMailer\OAuth;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Token\AccessToken;
use Exception;

class GoogleOAuthClient
{
    private Google $provider;
    public function __construct(array $config)
    {
        $this->provider = new Google([
            'clientId' => $config['clientId'],
            'clientSecret' => $config['clientSecret'],
            'redirectUri' => $config['redirectUri'],
            'accessType' => 'offline',
            'scopes' => ['https://mail.google.com/'] 
        ]);
    }
    public function getAuthorizationUrl(): string
    {
        $options = [
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        return $this->provider->getAuthorizationUrl($options);
    }
    public function getAccessToken(string $code): AccessToken
    {
        return $this->provider->getAccessToken('authorization_code', [
            'code' => $code
        ]);
    }
    public function refreshAccessToken(string $refreshToken): AccessToken
    {
        return $this->provider->getAccessToken('refresh_token', [
            'refresh_token' => $refreshToken
        ]);
    }
    public function getProvider(): Google
    {
        return $this->provider;
    }
}
?>