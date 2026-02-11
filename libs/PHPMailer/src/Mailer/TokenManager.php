<?php

namespace GoogleOAuthPHPMailer\Mailer;
use League\OAuth2\Client\Token\AccessToken;
use Exception;

class TokenManager
{
    private string $tokenFile;
    public function __construct(string $tokenFile)
    {
        $this->tokenFile = $tokenFile;
    }
    public function saveToken(AccessToken $token): void
    {
        $data = [
            'access_token' => $token->getToken(),
            'refresh_token' => $token->getRefreshToken(),
            'expires' => $token->getExpires()
        ];
        if (file_put_contents($this->tokenFile, json_encode($data, JSON_PRETTY_PRINT)) === false) {
            throw new Exception("Failed to write token to file.");
        }
    }
    public function getToken(): ?array
    {
        if (!file_exists($this->tokenFile)) {
            return null;
        }
        $data = json_decode(file_get_contents($this->tokenFile), true);
        return is_array($data) ? $data : null;
    }
}
?>