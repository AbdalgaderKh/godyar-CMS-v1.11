<?php

namespace GoogleOAuthPHPMailer\Config;

class Config
{
    public static function get(): array
    {
        return [
            'clientId' => 'YOUR_GOOGLE_CLIENT_ID',
            'clientSecret' => 'YOUR_GOOGLE_CLIENT_SECRET',
            'redirectUri' => 'http://localhost/your-app/public/oauth-callback.php',
            'fromEmail' => 'your-email@example.com',
            'fromName' => 'Your Name',
            'tokenPath' => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tokens' . DIRECTORY_SEPARATOR . 'tokens.json'
        ];
    }
}