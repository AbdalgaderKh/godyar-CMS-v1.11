<?php

namespace GoogleOAuthPHPMailer\Config;

class Config
{
    public static function get(): array
    {
        return [
            'clientId' => '772878678883-vopibndjtbn6m8uhnf0k427m77qj5osn.apps.googleusercontent.com',
            'clientSecret' => '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',
            'redirectUri' => 'http://localhost/oauth-phpmailer-package/public/oauth-callback-url.php',
            'fromEmail' => 'akashmasalhssdev@gmail.com',
            'fromName' => 'Akash Masal',
            'tokenPath' => dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tokens' . DIRECTORY_SEPARATOR . 'tokens.json'
        ];
    }
}
?>