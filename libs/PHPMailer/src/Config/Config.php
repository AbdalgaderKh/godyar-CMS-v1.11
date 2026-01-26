<?php

namespace GoogleOAuthPHPMailer\Config;

class Config
{
    public static function get(): array
    {
        return [
            'clientId' => 'YOUR_GOOGLE_CLIENT_ID',
            'clientSecret' => 'YOUR_GOOGLE_CLIENT_ID',
            'redirectUri' => 'http://localhost/oauth-phpmailer-package/public/oauth-callback-url.php',
            'fromEmail' => 'example@examplel.com',
            'fromName' => 'YOUR_name',
            'tokenPath' => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tokens' . DIRECTORY_SEPARATOR . 'tokens.json'
        ];
    }
}
?>